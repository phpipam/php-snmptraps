<?php

declare(strict_types=1);

namespace Snmptraps;

/**
 * Reads and modifies stored traps. Every read honours the per-user host restriction
 * passed in the constructor (null = all hosts).
 */
final class TrapRepository
{
    /**
     * @param list<string>|null $permittedHosts
     */
    public function __construct(private readonly Database $db, private readonly ?array $permittedHosts = null)
    {
    }

    /**
     * @param array{severities?:list<string>, hostname?:string, search?:string, text?:string, from?:string, to?:string, after_id?:int} $filter
     *   search   - list quick filter: message/content LIKE, severity/hostname exact
     *   text     - message/content LIKE
     * @return list<array<string,mixed>>
     */
    public function find(array $filter, int $limit = 50, int $offset = 0, string $order = 'desc'): array
    {
        [$where, $params] = $this->where($filter);
        $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
        $limit = max(1, min(5000, $limit));
        $offset = max(0, $offset);
        return $this->db->all("SELECT * FROM `traps` $where ORDER BY `id` $order LIMIT $limit OFFSET $offset", $params);
    }

    public function count(array $filter): int
    {
        [$where, $params] = $this->where($filter);
        return (int) $this->db->value("SELECT COUNT(*) FROM `traps` $where", $params);
    }

    /** @return array<string,mixed>|null */
    public function get(int $id): ?array
    {
        $row = $this->db->find('traps', $id);
        if ($row === null || !$this->permitted((string) $row['hostname'])) {
            return null;
        }
        return $row;
    }

    /** @return list<string> */
    public function hosts(): array
    {
        $hosts = $this->db->column("SELECT DISTINCT `hostname` FROM `traps` WHERE `hostname` IS NOT NULL AND `hostname` <> '' AND `hostname` <> '<UNKNOWN>' ORDER BY `hostname`");
        return array_values(array_filter(array_map('strval', $hosts), $this->permitted(...)));
    }

    /** @return list<array<string,mixed>> */
    public function byMessage(string $message, int $limit = 200): array
    {
        [$where, $params] = $this->where([]);
        $where = ($where === '' ? 'WHERE ' : $where . ' AND ') . '`message` = ?';
        $params[] = $message;
        $limit = max(1, min(5000, $limit));
        return $this->db->all("SELECT * FROM `traps` $where ORDER BY `id` DESC LIMIT $limit", $params);
    }

    public function permitted(string $hostname): bool
    {
        return $this->permittedHosts === null || in_array($hostname, $this->permittedHosts, true);
    }

    /** @return array{0:string, 1:list<mixed>} */
    private function where(array $f): array
    {
        $w = [];
        $p = [];

        if ($this->permittedHosts !== null) {
            if ($this->permittedHosts === []) {
                $w[] = '1 = 0';
            } else {
                $w[] = '`hostname` IN (' . Database::placeholders($this->permittedHosts) . ')';
                array_push($p, ...$this->permittedHosts);
            }
        }
        if (!empty($f['severities'])) {
            $w[] = '`severity` IN (' . Database::placeholders($f['severities']) . ')';
            array_push($p, ...array_values($f['severities']));
        }
        if (isset($f['hostname']) && $f['hostname'] !== '') {
            $w[] = '`hostname` = ?';
            $p[] = $f['hostname'];
        }
        if (isset($f['text']) && $f['text'] !== '') {
            $like = '%' . self::escapeLike($f['text']) . '%';
            $w[] = '(`message` LIKE ? OR `content` LIKE ?)';
            array_push($p, $like, $like);
        }
        if (isset($f['search']) && $f['search'] !== '') {
            $like = '%' . self::escapeLike($f['search']) . '%';
            $w[] = '(`message` LIKE ? OR `content` LIKE ? OR `severity` = ? OR `hostname` = ?)';
            array_push($p, $like, $like, $f['search'], $f['search']);
        }
        if (!empty($f['from'])) {
            $w[] = '`date` >= ?';
            $p[] = $f['from'];
        }
        if (!empty($f['to'])) {
            $w[] = '`date` <= ?';
            $p[] = $f['to'];
        }
        if (isset($f['after_id'])) {
            $w[] = '`id` > ?';
            $p[] = (int) $f['after_id'];
        }
        return [$w === [] ? '' : 'WHERE ' . implode(' AND ', $w), $p];
    }

    private static function escapeLike(string $s): string
    {
        return addcslashes($s, '%_\\');
    }

    // ---- operator actions ---------------------------------------------------------

    /**
     * Define (create/update) a severity definition and re-classify matching stored traps.
     * Content empty = whole OID, otherwise only traps whose message equals content.
     *
     * @return int number of re-classified traps
     */
    public function defineSeverity(string $oid, string $severity, string $content = '', string $comment = ''): int
    {
        $existing = $this->db->one(
            'SELECT `id` FROM `severity_definitions` WHERE `oid` = ? AND IFNULL(`content`, \'\') = ? LIMIT 1',
            [$oid, $content]
        );
        $row = ['oid' => $oid, 'severity' => $severity, 'content' => $content === '' ? null : $content];
        if ($comment !== '') {
            $row['comment'] = $comment;
        }
        if ($existing === null) {
            $this->db->insert('severity_definitions', $row);
        } else {
            $this->db->update('severity_definitions', (int) $existing['id'], $row);
        }

        if ($content === '') {
            return $this->db->run('UPDATE `traps` SET `severity` = ? WHERE `oid` = ?', [$severity, $oid])->rowCount();
        }
        return $this->db->run('UPDATE `traps` SET `severity` = ? WHERE `oid` = ? AND `message` = ?', [$severity, $oid, $content])->rowCount();
    }

    /** @return int removed traps */
    public function deleteByOid(string $oid, string $content = ''): int
    {
        if ($content === '') {
            return $this->db->run('DELETE FROM `traps` WHERE `oid` = ?', [$oid])->rowCount();
        }
        return $this->db->run('DELETE FROM `traps` WHERE `oid` = ? AND `content` LIKE ?', [$oid, '%' . self::escapeLike($content) . '%'])->rowCount();
    }

    /**
     * Create an exception (ignore rule) and remove already stored matching traps.
     *
     * @return int removed traps
     */
    public function addException(string $oid, string $hostname, string $content = '', string $comment = ''): int
    {
        $this->db->insert('exceptions', [
            'oid'      => $oid,
            'hostname' => $hostname,
            'content'  => $content === '' ? null : $content,
            'comment'  => $comment === '' ? null : $comment,
        ]);

        $sql = 'DELETE FROM `traps` WHERE `oid` = ?';
        $params = [$oid];
        if ($hostname !== 'all') {
            $sql .= ' AND `hostname` = ?';
            $params[] = $hostname;
        }
        if ($content !== '') {
            $sql .= ' AND `content` LIKE ?';
            $params[] = '%' . self::escapeLike($content) . '%';
        }
        return $this->db->run($sql, $params)->rowCount();
    }
}
