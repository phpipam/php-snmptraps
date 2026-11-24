<?php

/**
 * Recompute fingerprint / repeats / first_date / superseded for all stored traps
 * with the current config ($repeat_window_minutes). Needs migration 1.0.3.
 *
 * Run after applying db/migrations/1.0.3.sql, or after changing $repeat_window_minutes:
 *   php bin/rebuild-repeats.php
 *
 * Safe while traps keep arriving (rows are written in chunks of CHUNK ids).
 */

declare(strict_types=1);

use Snmptraps\Database;
use Snmptraps\Schema;
use Snmptraps\Trap\Repeats;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const CHUNK = 5000;

$config = require __DIR__ . '/../src/bootstrap.php';
$db = Database::fromConfig($config);
if (!(new Schema($db, 'rebuild'))->has('1.0.3')) {
    fwrite(STDERR, "Migration 1.0.3 is not applied - run db/migrations/1.0.3.sql first.\n");
    exit(1);
}
$repeats = new Repeats(0, $config->repeatWindowMinutes);

$db->run('CREATE TEMPORARY TABLE `tmp_repeats` (
    `id` int(11) unsigned NOT NULL PRIMARY KEY,
    `fingerprint` char(32) NOT NULL,
    `repeats` int(10) unsigned NOT NULL,
    `first_date` timestamp NULL DEFAULT NULL,
    `superseded` tinyint(1) NOT NULL
) ENGINE=MEMORY');

/** @var array<string, array{id:int, repeats:int, first_date:?string, date:string, ts:int}> $last  latest trap per fingerprint */
$last = [];
$lastId = 0;
$total = 0;
$started = microtime(true);

do {
    $rows = $db->all('SELECT `id`, `hostname`, `oid`, `content`, `date` FROM `traps` WHERE `id` > ? ORDER BY `id` LIMIT ' . CHUNK, [$lastId]);
    /** @var array<int, array{0:string,1:int,2:?string,3:int}> $out id => [fingerprint, repeats, first_date, superseded] */
    $out = [];
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        $ts = (int) strtotime((string) $r['date']);
        $fp = Repeats::fingerprint((string) $r['hostname'], (string) $r['oid'], (string) $r['content']);
        $prev = $last[$fp] ?? null;
        $state = $repeats->evaluate($prev, $prev === null ? 0 : $ts - $prev['ts']);
        if ($state['supersedes'] !== null) {
            // the previous trap may have been written in an earlier chunk - write it again
            $out[$prev['id']] = [$fp, $prev['repeats'], $prev['first_date'], 1];
        }
        $out[$id] = [$fp, $state['repeats'], $state['first_date'], 0];
        $last[$fp] = ['id' => $id, 'repeats' => $state['repeats'], 'first_date' => $state['first_date'], 'date' => (string) $r['date'], 'ts' => $ts];
        $lastId = $id;
    }
    if ($out !== []) {
        $values = [];
        $params = [];
        foreach ($out as $id => [$fp, $rep, $first, $sup]) {
            $values[] = '(?, ?, ?, ?, ?)';
            array_push($params, $id, $fp, $rep, $first, $sup);
        }
        $db->run('INSERT INTO `tmp_repeats` VALUES ' . implode(',', $values), $params);
        $db->run('UPDATE `traps` t JOIN `tmp_repeats` r ON r.`id` = t.`id`
                  SET t.`fingerprint` = r.`fingerprint`, t.`repeats` = r.`repeats`, t.`first_date` = r.`first_date`, t.`superseded` = r.`superseded`');
        $db->run('DELETE FROM `tmp_repeats`');
    }
    $total += count($rows);
    fprintf(STDOUT, "\r%d traps processed", $total);
} while (count($rows) === CHUNK);

printf("\n%d traps, %d fingerprints, %.1fs\n", $total, count($last), microtime(true) - $started);
