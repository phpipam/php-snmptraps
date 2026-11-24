<?php

declare(strict_types=1);

namespace Snmptraps\Trap;

use Snmptraps\Config;
use Snmptraps\Database;
use Snmptraps\Logger;
use Snmptraps\Notify\Notifier;
use Snmptraps\Schema;

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
        $repeats = Repeats::fromConfig($this->config);
        $state = null;
        // repeat tracking needs migration 1.0.3; without it traps are stored and notified as before
        if ($repeats->enabled() && (new Schema($this->db, $this->config->db['name']))->has('1.0.3')) {
            $row['fingerprint'] = Repeats::fingerprint($row['hostname'], $row['oid'], $row['content']);
            $prev = $this->db->one(
                'SELECT `id`, `repeats`, `first_date`, `date`, TIMESTAMPDIFF(SECOND, `date`, NOW()) AS `age` FROM `traps`
                 WHERE `fingerprint` = ? AND `date` >= NOW() - INTERVAL ? MINUTE
                 ORDER BY `date` DESC, `id` DESC LIMIT 1',
                [$row['fingerprint'], $repeats->lookbackMinutes()],
            );
            $state = $repeats->evaluate($prev, (int) ($prev['age'] ?? 0));
            $row['repeats'] = $state['repeats'];
            $row['first_date'] = $state['first_date'];
        }

        $id = $this->db->insert('traps', $row);

        if ($state !== null && $state['supersedes'] !== null) {
            // only the latest trap of a repeat chain is listed
            $this->db->run('UPDATE `traps` SET `superseded` = 1 WHERE `id` = ?', [$state['supersedes']]);
        }
        if ($state !== null && $state['duplicate']) {
            if ($this->config->writeDebugFile) {
                $this->log->info("{$trap->hostname} {$trap->oid} - notification skipped: identical trap within {$this->config->notificationDedupMinutes} min");
            }
            return;
        }
        (new Notifier($this->config, $this->db, $this->log))->notify($trap, $id);
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
