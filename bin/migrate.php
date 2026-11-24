<?php

/**
 * php-snmptraps database migrations.
 *
 * Checks which db/migrations/<version>.sql files are not applied (detected from the live
 * schema, see Snmptraps\Schema) and applies them in version order, followed by their
 * post-migration step (e.g. bin/rebuild-repeats.php for 1.0.3).
 *
 *   php bin/migrate.php            show status, ask, apply pending migrations
 *   php bin/migrate.php --check    show status only; exit code 1 if migrations are pending
 *   php bin/migrate.php --yes      apply without asking (cron / deploy scripts)
 *   php bin/migrate.php --install  empty database: load db/SCHEMA.sql (fresh install)
 *
 * Uses the database credentials of config.php; the user needs ALTER / INDEX / CREATE / DROP.
 * Back up the database first - DDL statements cannot be rolled back. The 1.0.2+ migrations
 * are re-runnable after a partial failure.
 */

declare(strict_types=1);

use Snmptraps\Database;
use Snmptraps\Schema;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$opts = getopt('hy', ['check', 'yes', 'install', 'help']);
if (isset($opts['h']) || isset($opts['help'])) {
    echo preg_replace('/^ \* ?/m', '', explode('*/', explode('/**', file_get_contents(__FILE__), 2)[1], 2)[0]);
    exit(0);
}
$checkOnly = isset($opts['check']);
$assumeYes = isset($opts['yes']) || isset($opts['y']);

$root = dirname(__DIR__);
$config = require $root . '/src/bootstrap.php';
$db = Database::fromConfig($config);
$schema = new Schema($db, $config->db['name']);
$dir = $root . '/db/migrations/';

$out = static function (string $msg = ''): void {
    fwrite(STDOUT, $msg . PHP_EOL);
};
$fail = static function (string $msg): never {
    fwrite(STDERR, "ERROR: $msg" . PHP_EOL);
    exit(2);
};

$out("php-snmptraps " . APP_VERSION . " - database {$config->db['user']}@{$config->db['host']}:{$config->db['port']}/{$config->db['name']}");

// ---- fresh install
if ($schema->isEmpty()) {
    if (!isset($opts['install'])) {
        $fail('the database has no snmptraps tables - run with --install to load db/SCHEMA.sql');
    }
    if ($checkOnly) {
        exit(1);
    }
    $out('Loading db/SCHEMA.sql ...');
    runSqlFile($db, $root . '/db/SCHEMA.sql', $out);
    Schema::clearCache();
    $out('Done. Default login: Admin / snmptraps - change the password after the first login.');
    exit(0);
}
if (isset($opts['install'])) {
    $fail('--install is only for an empty database, this one already has snmptraps tables');
}

// ---- status
$pending = $schema->pending(false);
foreach (Schema::MIGRATIONS as $ver => $what) {
    $out(sprintf('  %-7s %-8s %s', $ver, in_array($ver, $pending, true) ? 'PENDING' : 'ok', $what));
}
foreach (glob($dir . '*.sql') ?: [] as $f) {
    $ver = basename($f, '.sql');
    if (!isset(Schema::MIGRATIONS[$ver])) {
        $out("  warning: $ver.sql has no detection check in Schema::MIGRATIONS - not applied by this script");
    }
}
if ($pending === []) {
    $out('Database schema is up to date.');
    exit(0);
}
usort($pending, 'version_compare');
foreach ($pending as $ver) {
    if (!is_file($dir . "$ver.sql")) {
        $fail("db/migrations/$ver.sql is missing");
    }
}
if ($checkOnly) {
    $out('Pending: ' . implode(', ', $pending) . ' - run php bin/migrate.php to apply.');
    exit(1);
}

// ---- confirm
if (!$assumeYes) {
    if (!stream_isatty(STDIN)) {
        $fail('not a terminal - use --yes to apply without confirmation');
    }
    fwrite(STDOUT, 'Apply ' . implode(', ', $pending) . '? Back up the database first. [y/N] ');
    if (strtolower(trim((string) fgets(STDIN))) !== 'y') {
        $out('Aborted.');
        exit(1);
    }
}

// ---- apply
foreach ($pending as $ver) {
    $out("Applying $ver.sql ...");
    $started = microtime(true);
    try {
        runSqlFile($db, $dir . "$ver.sql", $out);
    } catch (Throwable $e) {
        $fail("$ver.sql failed: " . $e->getMessage() . "\nThe migration may be partially applied - fix the cause and run the script again.");
    }
    if (isset(Schema::POST_MIGRATION[$ver])) {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/' . Schema::POST_MIGRATION[$ver]);
        $out('  running ' . Schema::POST_MIGRATION[$ver] . ' ...');
        passthru($cmd, $code);
        if ($code !== 0) {
            $fail(Schema::POST_MIGRATION[$ver] . " failed (exit $code)");
        }
    }
    if (in_array($ver, $schema->pending(false), true)) {
        $fail("$ver.sql ran but the schema check still reports it as missing");
    }
    $out(sprintf('  %s done in %.1fs', $ver, microtime(true) - $started));
}
Schema::clearCache();
$out('Database schema is up to date.');
exit(0);

/**
 * Execute a .sql file statement by statement (full-line "--" comments are skipped,
 * statements end with ";" at the end of a line).
 */
function runSqlFile(Database $db, string $file, callable $out): void
{
    $sql = (string) file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $t = microtime(true);
        // exec, not prepare: DDL / SET statements are not all preparable
        $n = (int) $db->pdo()->exec($stmt);
        $out(sprintf('    %-70s %6.1fs%s', mb_strimwidth(preg_replace('/\s+/', ' ', $stmt) ?? '', 0, 70, '...'), microtime(true) - $t, $n > 0 ? " ($n rows)" : ''));
    }
}
