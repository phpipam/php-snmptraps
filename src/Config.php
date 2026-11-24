<?php

declare(strict_types=1);

namespace Snmptraps;

use RuntimeException;

/**
 * Typed view of config.php.
 *
 * config.php keeps its historic format (plain PHP globals + BASE constant) so existing
 * installations keep working; it is included in an isolated scope and read once.
 */
final class Config
{
    /** @var array{host:string,user:string,pass:string,name:string,port:int,ssl?:bool,ssl_key?:string,ssl_cert?:string,ssl_ca?:string,ssl_capath?:string,ssl_cipher?:string} */
    public readonly array $db;
    public readonly bool $debugging;
    public readonly string $sessionName;
    /** URL path prefix of the web UI, always with leading and trailing slash (e.g. "/snmptraps/") */
    public readonly string $base;
    /** Absolute URL of the web UI used in notifications, always with trailing slash */
    public readonly string $url;
    public readonly array $ad;
    public readonly string|false $logFile;
    public readonly bool $writeRawFile;
    public readonly bool $writeDebugFile;
    public readonly string $mibDirectory;
    /** @var list<string> */
    public readonly array $notificationMethods;
    public readonly array $notificationParams;
    public readonly string $title;
    public readonly string $footer;
    public readonly bool $stripHostnameDomain;
    /** no notifications when an identical trap (host, OID, content) arrived within this many minutes, 0 = off */
    public readonly int $notificationDedupMinutes;
    /** identical traps closer than this many minutes to the previous one count as repeats (GUI badge), 0 = off */
    public readonly int $repeatWindowMinutes;
    /** $screenshot_mode = true in config.php: mask hostnames / IPs in the UI for every user (not in config.dist.php) */
    public readonly bool $screenshotMode;

    private function __construct(array $c)
    {
        $db = $c['db'] ?? null;
        if (!is_array($db) || !isset($db['host'], $db['user'], $db['name'])) {
            throw new RuntimeException('config.php: $db array (host, user, pass, name) is required');
        }
        $db['pass'] = (string) ($db['pass'] ?? '');
        $db['port'] = (int) ($db['port'] ?? 3306);
        $this->db = $db;

        $this->debugging   = (bool) ($c['debugging'] ?? false);
        $this->sessionName = preg_match('/^[A-Za-z0-9_]{1,64}$/', (string) ($c['phpsessname'] ?? '')) ? $c['phpsessname'] : 'snmptraps';

        $base = defined('BASE') ? (string) constant('BASE') : '/';
        $base = preg_replace('#/?index\.php$#', '/', $base) ?? '/';
        $this->base = '/' . trim($base, '/') . (trim($base, '/') === '' ? '' : '/');

        $url = trim((string) ($c['url'] ?? ''));
        // repair the common "http:/host" typo
        $url = preg_replace('#^(https?):/(?!/)#', '$1://', $url) ?? $url;
        $this->url = $url === '' ? '' : rtrim($url, '/') . '/';

        $this->ad             = is_array($c['ad'] ?? null) ? $c['ad'] : [];
        $this->logFile        = (isset($c['filename']) && is_string($c['filename']) && $c['filename'] !== '') ? $c['filename'] : false;
        $this->writeRawFile   = (bool) ($c['write_raw_file'] ?? false);
        $this->writeDebugFile = (bool) ($c['write_debug_file'] ?? false);
        $mibDir = $c['mib_directory'] ?? null;
        if (!is_string($mibDir) || $mibDir === '') {
            // net-snmp default locations (FreeBSD ports / Linux packages)
            $mibDir = is_dir('/usr/local/share/snmp/mibs') ? '/usr/local/share/snmp/mibs' : '/usr/share/snmp/mibs';
        }
        $this->mibDirectory   = rtrim($mibDir, '/') . '/';

        $methods = $c['notification_methods'] ?? [];
        $this->notificationMethods = is_array($methods) ? array_values(array_map('strval', $methods)) : [];
        $this->notificationParams  = is_array($c['notification_params'] ?? null) ? $c['notification_params'] : [];

        $this->title               = (string) ($c['title'] ?? 'Snmptraps server');
        $this->footer              = (string) ($c['footer'] ?? 'php-snmptraps');
        $this->stripHostnameDomain = (bool) ($c['strip_hostname_domain'] ?? false);
        $this->notificationDedupMinutes = max(0, (int) ($c['notification_dedup_minutes'] ?? 15));
        $this->repeatWindowMinutes      = max(0, (int) ($c['repeat_window_minutes'] ?? 10));
        $this->screenshotMode           = (bool) ($c['screenshot_mode'] ?? false);
    }

    public static function load(string $file): self
    {
        if (!is_file($file)) {
            throw new RuntimeException('config.php file missing! Copy config.dist.php to config.php and edit it.');
        }
        $vars = (static function (string $__file): array {
            require $__file;
            unset($__file);
            return get_defined_vars();
        })($file);

        return new self($vars);
    }
}
