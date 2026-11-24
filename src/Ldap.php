<?php

declare(strict_types=1);

namespace Snmptraps;

use RuntimeException;

/**
 * Minimal Active Directory / LDAP bind authenticator (requires the php ldap extension).
 *
 * Config ($ad in config.php):
 *   domain_controllers  list of hosts
 *   account_suffix      "domain.local" (appended as user@domain.local unless the user gives a UPN/DOMAIN\user)
 *   ad_port             389 / 636
 *   use_ssl             ldaps://
 *   use_tls             StartTLS
 *   timeout             network timeout in seconds (default 5)
 */
final class Ldap
{
    public function __construct(private readonly array $config)
    {
        if (!function_exists('ldap_connect')) {
            throw new RuntimeException('PHP ldap extension is not installed');
        }
        if (empty($config['domain_controllers'])) {
            throw new RuntimeException('Invalid directory configuration');
        }
    }

    public function authenticate(string $username, string $password): bool
    {
        // reject empty password (anonymous bind) and control characters
        if ($username === '' || $password === '' || preg_match('/[\x00-\x1f]/', $username . $password)) {
            return false;
        }

        $suffix = (string) ($this->config['account_suffix'] ?? '');
        if ($suffix !== '' && $suffix[0] !== '@') {
            $suffix = '@' . $suffix;
        }
        $bindUser = (!str_contains($username, '@') && !str_contains($username, '\\')) ? $username . $suffix : $username;

        $ssl     = !empty($this->config['use_ssl']);
        $port    = (int) ($this->config['ad_port'] ?? ($ssl ? 636 : 389));
        $timeout = (int) ($this->config['timeout'] ?? 5);

        $reached = false;
        foreach ((array) $this->config['domain_controllers'] as $host) {
            $conn = @ldap_connect(($ssl ? 'ldaps://' : 'ldap://') . $host . ':' . $port);
            if ($conn === false) {
                continue;
            }
            ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, $timeout);

            if (!$ssl && !empty($this->config['use_tls']) && !@ldap_start_tls($conn)) {
                @ldap_unbind($conn);
                continue;
            }

            $ok    = @ldap_bind($conn, $bindUser, $password);
            $errno = ldap_errno($conn);
            @ldap_unbind($conn);

            if ($ok) {
                return true;
            }
            // 49 invalid credentials, 50 insufficient access, 53 unwilling to perform
            if (in_array($errno, [49, 50, 53], true)) {
                return false;
            }
            if ($errno !== -1) {
                $reached = true;
            }
        }

        if ($reached) {
            return false;
        }
        throw new RuntimeException('Cannot connect to directory server');
    }
}
