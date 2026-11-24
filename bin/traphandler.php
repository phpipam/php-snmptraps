<?php

/**
 * php-snmptraps trap receiver.
 *
 * snmptrapd.conf:
 *   traphandle default /usr/local/bin/php /usr/local/www/snmptraps/bin/traphandler.php
 *
 * Reads one trap from STDIN, stores it and sends notifications.
 *
 * Test:
 *   snmptrap -v 2c -c public <server> '' .1.3.6.1.4.1.2636.4.1.1 .1.3.6.1.4.1.2636.4.1.1 s "test trap"
 * or replay a stored raw trap without snmptrapd:
 *   printf 'host\nUDP: [10.0.0.1]:1->[10.0.0.2]:162\nX::y 1\nSNMPv2-MIB::snmpTrapOID.0 IF-MIB::linkDown\n' | php bin/traphandler.php
 */

declare(strict_types=1);

use Snmptraps\Database;
use Snmptraps\Logger;
use Snmptraps\Trap\TrapProcessor;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$logger = null;
try {
    $config = require __DIR__ . '/../src/bootstrap.php';
    $logger = new Logger($config->logFile);

    $lines = [];
    while (($line = fgets(STDIN)) !== false) {
        $lines[] = $line;
    }
    if ($lines === []) {
        throw new RuntimeException('empty trap on STDIN');
    }

    (new TrapProcessor($config, Database::fromConfig($config), $logger))->process($lines);
} catch (Throwable $e) {
    $msg = get_class($e) . ': ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
    $logger !== null ? $logger->error($msg) : error_log('snmptraps: ' . $msg);
    fwrite(STDERR, $msg . PHP_EOL);
    exit(1);
}
