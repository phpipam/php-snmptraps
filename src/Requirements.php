<?php

declare(strict_types=1);

namespace Snmptraps;

/**
 * PHP extensions the application needs. Checked by the web front controller
 * (public/index.php, every request) and bin/migrate.php. The PHP version itself is
 * checked in bootstrap.php (APP_MIN_PHP).
 *
 * Distributions that package extensions separately (FreeBSD php83-*, Debian php8.3-*)
 * may load a different set for the CLI and for php-fpm - each SAPI checks its own.
 */
final class Requirements
{
    /** required extension => what needs it */
    public const REQUIRED = [
        'pdo'       => 'database access',
        'pdo_mysql' => 'database access (MySQL / MariaDB driver)',
        'mbstring'  => 'multibyte string handling',
        'ctype'     => 'input validation, PHPMailer',
        'filter'    => 'input validation, PHPMailer',
        'json'      => 'schema cache, notifications',
        'openssl'   => 'SMTP TLS, HTTPS notification gateways (Pushover, Slack, SMS)',
        'session'   => 'web UI login',
    ];

    /** optional extension => feature that is unavailable without it */
    public const OPTIONAL = [
        'ldap' => 'Active Directory login (users with auth method "ad")',
    ];

    /** extensions only the web UI needs (not checked for CLI scripts) */
    private const WEB_ONLY = ['session'];

    /**
     * Missing required extensions.
     *
     * @param bool $web true = web UI, false = CLI scripts (web-only extensions are skipped)
     * @return array<string, string> extension => what needs it
     */
    public static function missing(bool $web = true): array
    {
        $missing = [];
        foreach (self::REQUIRED as $ext => $why) {
            if (!$web && in_array($ext, self::WEB_ONLY, true)) {
                continue;
            }
            if (!extension_loaded($ext)) {
                $missing[$ext] = $why;
            }
        }
        return $missing;
    }

    /** @return array<string, string> missing optional extensions: extension => unavailable feature */
    public static function missingOptional(): array
    {
        return array_filter(self::OPTIONAL, static fn (string $ext): bool => !extension_loaded($ext), ARRAY_FILTER_USE_KEY);
    }
}
