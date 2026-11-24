<?php

declare(strict_types=1);

namespace Snmptraps;

use Throwable;

/**
 * Detects database migrations (db/migrations/<version>.sql) that are not applied yet,
 * by inspecting the live schema. The result is cached for a minute in the system temp dir
 * (per OS user, the receiver and the web server run as different users).
 */
final class Schema
{
    /** version => what the migration adds; keep in sync with db/migrations/ */
    public const MIGRATIONS = [
        '1.0.0' => 'maintenance table, users / traps cleanup and indexes',
        '1.0.1' => 'dashboard layout default (20 traps per severity)',
        '1.0.2' => 'traps.repeats (repeat counter)',
        '1.0.3' => 'traps.fingerprint / first_date / superseded (collapsed repeats)',
    ];

    private const CACHE_TTL = 60;

    /** @var list<string>|null */
    private ?array $pending = null;

    public function __construct(private readonly Database $db, private readonly string $cacheKey = 'default')
    {
    }

    /** @return list<string> versions of migrations that are not applied */
    public function pending(): array
    {
        if ($this->pending !== null) {
            return $this->pending;
        }
        $cache = sys_get_temp_dir() . '/snmptraps-schema-' . (function_exists('posix_geteuid') ? posix_geteuid() : 'u') . '-' . md5($this->cacheKey) . '.json';
        if (is_file($cache) && filemtime($cache) > time() - self::CACHE_TTL) {
            $data = json_decode((string) @file_get_contents($cache), true);
            if (is_array($data) && ($data['versions'] ?? null) === array_keys(self::MIGRATIONS) && is_array($data['pending'] ?? null)) {
                return $this->pending = $data['pending'];
            }
        }
        $this->pending = $this->detect();
        @file_put_contents($cache, json_encode(['versions' => array_keys(self::MIGRATIONS), 'pending' => $this->pending]), LOCK_EX);
        return $this->pending;
    }

    public function has(string $version): bool
    {
        return !in_array($version, $this->pending(), true);
    }

    /** @return list<string> */
    private function detect(): array
    {
        try {
            $cols = [];
            foreach ($this->db->all(
                "SELECT `TABLE_NAME` AS t, `COLUMN_NAME` AS c, `COLUMN_DEFAULT` AS d FROM information_schema.`COLUMNS`
                 WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` IN ('traps', 'users', 'maintenance')",
            ) as $r) {
                $cols[$r['t']][$r['c']] = (string) $r['d'];
            }
            $keys = $this->db->column(
                "SELECT DISTINCT `INDEX_NAME` FROM information_schema.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'traps'",
            );
        } catch (Throwable) {
            return [];   // cannot inspect - do not block anything
        }
        $traps = $cols['traps'] ?? [];
        $checks = [
            '1.0.0' => isset($cols['maintenance']),
            '1.0.1' => str_contains($cols['users']['dash_layout'] ?? '', '6:20'),
            '1.0.2' => isset($traps['repeats']),
            '1.0.3' => isset($traps['fingerprint'], $traps['first_date'], $traps['superseded']) && in_array('fingerprint', $keys, true),
        ];
        return array_keys(array_filter($checks, static fn ($ok) => !$ok));
    }
}
