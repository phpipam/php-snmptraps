<?php

/**
 * php-snmptraps web front controller. This directory (public/) is the only one
 * exposed by the web server; every non-asset request is routed here.
 */

declare(strict_types=1);

use Snmptraps\Web\App;

ob_start();

try {
    $config = require __DIR__ . '/../src/bootstrap.php';
} catch (Throwable $e) {
    http_response_code(500);
    error_log('snmptraps: ' . $e->getMessage());
    exit('Configuration error: ' . htmlspecialchars($e->getMessage()));
}

(new App($config))->run();

ob_end_flush();
