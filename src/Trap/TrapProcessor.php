<?php

declare(strict_types=1);

namespace Snmptraps\Trap;

use Snmptraps\Config;
use Snmptraps\Database;
use Snmptraps\Logger;
use Snmptraps\Notify\Notifier;

/**
 * Receives one trap from snmptrapd: parse -> exception check -> repeat check -> store -> notify
 * (notifications are skipped when an identical trap arrived within notification_dedup_minutes).
 */
final class TrapProcessor
{
    private const MAX_LEN = ['hostname' => 128, 'ip' => 45, 'oid' => 255, 'message' => 1024, 'severity' => 32];

    public function __construct(
        private readonly Config $config,
        private readonly Database $db,
        private readonly Logger $log,
    ) {
    }

    /** @param list<string> $lines */
    public function process(array $lines): void
    {
        $definitions = $this->db->all('SELECT `oid`, `severity` FROM `severity_definitions` ORDER BY `id`');
        $trap = (new TrapParser($definitions))->parse($lines);

        if ($this->config->writeRawFile) {
            $this->log->raw("---- RAW ----\n" . $trap->raw);
        }
        if ($this->config->writeDebugFile) {
            $this->log->raw($this->debugDump($trap));
        }

        $exception = $this->matchException($trap);
        if ($exception !== null) {
            if ($this->config->writeDebugFile) {
                $this->log->info("{$trap->hostname} {$trap->oid} ignored by exception #{$exception}");
            }
            return;
        }

        $row = $this->row($trap);
        [$repeats, $duplicate] = $this->previousIdentical($row);
        $row['repeats'] = $repeats;
        $id = $this->db->insert('traps', $row);

        if ($duplicate) {
            if ($this->config->writeDebugFile) {
                $this->log->info("{$trap->hostname} {$trap->oid} - notification skipped: identical trap within {$this->config->notificationDedupMinutes} min");
            }
            return;
        }
        (new Notifier($this->config, $this->db, $this->log))->notify($trap, $id);
    }

    /**
     * Looks up the latest stored trap with the same hostname, OID and content.
     *
     * @return array{0:int, 1:bool} [repeat counter for the new trap, suppress notifications]
     */
    private function previousIdentical(array $row): array
    {
        $dedup = $this->config->notificationDedupMinutes;
        $window = $this->config->repeatWindowMinutes;
        $lookback = max($dedup, $window);
        if ($lookback === 0) {
            return [0, false];
        }
        $prev = $this->db->one(
            'SELECT `repeats`, TIMESTAMPDIFF(SECOND, `date`, NOW()) AS `age` FROM `traps`
             WHERE `hostname` = ? AND `oid` = ? AND `content` = ? AND `date` >= NOW() - INTERVAL ? MINUTE
             ORDER BY `date` DESC, `id` DESC LIMIT 1',
            [$row['hostname'], $row['oid'], $row['content'], $lookback],
        );
        if ($prev === null) {
            return [0, false];
        }
        $age = (int) $prev['age'];
        $repeats = ($window > 0 && $age < $window * 60) ? (int) $prev['repeats'] + 1 : 0;
        return [$repeats, $dedup > 0 && $age < $dedup * 60];
    }

    /**
     * @return int|null id of the matching exception rule
     */
    private function matchException(Trap $trap): ?int
    {
        $content = $trap->contentText();
        foreach ($this->db->all('SELECT `id`, `oid`, `hostname`, `content` FROM `exceptions` ORDER BY `id`') as $exc) {
            $host = (string) $exc['hostname'];
            if ($host !== $trap->hostname && $host !== 'all') {
                continue;
            }
            if (!str_contains($trap->oid, (string) $exc['oid'])) {
                continue;
            }
            $match = (string) ($exc['content'] ?? '');
            if ($match === '' || str_contains($content, $match)) {
                return (int) $exc['id'];
            }
        }
        return null;
    }

    /** @return array<string,string> traps row, truncated to the column lengths (STRICT mode) */
    private function row(Trap $trap): array
    {
        $row = [
            'hostname' => $trap->hostname,
            'ip'       => $trap->ip,
            'oid'      => $trap->oid,
            'message'  => $trap->message,
            'severity' => $trap->severity,
            'content'  => $trap->contentText(),
            'raw'      => $trap->raw,
        ];
        foreach (self::MAX_LEN as $col => $len) {
            $row[$col] = mb_substr($row[$col], 0, $len);
        }
        return $row;
    }

    private function debugDump(Trap $trap): string
    {
        $out = ["\n" . date('Y-m-d H:i:s') . ':'];
        foreach ($trap->toArray() as $k => $v) {
            if ($k === 'content') {
                $out[] = "content => array:";
                foreach ($trap->content as $c) {
                    $out[] = "\t" . $c;
                }
            } else {
                $out[] = "$k => $v";
            }
        }
        $out[] = '-----';
        return implode("\n", $out);
    }
}
