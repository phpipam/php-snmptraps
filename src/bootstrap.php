<?php

declare(strict_types=1);

/**
 * Application bootstrap - shared by the web front controller (public/index.php)
 * and the CLI trap receiver (bin/traphandler.php).
 *
 * Returns the loaded Config object.
 */

use Snmptraps\Config;

const APP_ROOT = __DIR__ . '/..';
const APP_VERSION = '1.1.0';
const APP_MIN_PHP = '8.3.0';

if (version_compare(PHP_VERSION, APP_MIN_PHP, '<')) {
    $msg = 'php-snmptraps requires PHP ' . APP_MIN_PHP . ' or later, you are running ' . PHP_VERSION;
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    exit(htmlspecialchars($msg));
}

// Composer autoloader: Snmptraps\ (src/) and the libraries in vendor/ (committed, no composer
// run needed on the server; maintainers update them with composer update, see composer.json)
if (!is_file(APP_ROOT . '/vendor/autoload.php')) {
    $msg = 'vendor/autoload.php is missing - run "composer install" in ' . realpath(APP_ROOT);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    exit(htmlspecialchars($msg));
}
require APP_ROOT . '/vendor/autoload.php';

require __DIR__ . '/helpers.php';

$config = Config::load(APP_ROOT . '/config.php');

error_reporting(E_ALL);
ini_set('display_errors', $config->debugging ? '1' : '0');
ini_set('log_errors', '1');

return $config;
