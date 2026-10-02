<?php

/**
 * Minimal LDAP / Active Directory authenticator (bind only).
 *
 * Uses PHP's ldap extension. Config array keys (same as the old adLDAP config):
 *   domain_controllers  array of hosts (tried in order)
 *   ad_port             port (default 389, or 636 if use_ssl)
 *   account_suffix      appended to username, e.g. "@domain.local" ("@" added if missing)
 *   base_dn             unused for binding, kept for compatibility
 *   use_ssl             bool, ldaps:// (optional)
 *   use_tls             bool, StartTLS (optional)
 *   timeout             connect/network timeout in seconds (default 5)
 */
class Ldap_auth {

    private $config;

    public function __construct ($config) {
        if (!function_exists('ldap_connect')) {
            throw new Exception(_("PHP ldap extension is not installed"));
        }
        if (!is_array($config) || empty($config['domain_controllers'])) {
            throw new Exception(_("Invalid directory configuration"));
        }
        $this->config = $config;
    }

    /**
     * Try to bind as user. Returns true on success, false on bad credentials.
     * Throws Exception if no directory server could be reached.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function authenticate ($username, $password) {
        // empty password would result in an unauthenticated (anonymous) bind that "succeeds"
        if ($username === "" || $password === "" || preg_match('/[\x00-\x1f]/', $username.$password)) {
            return false;
        }

        $suffix = isset($this->config['account_suffix']) ? (string) $this->config['account_suffix'] : "";
        if ($suffix !== "" && $suffix[0] !== "@" && strpos($username, "@") === false) {
            $suffix = "@".$suffix;
        }
        $bind_user = strpos($username, "@") === false && strpos($username, "\\") === false ? $username.$suffix : $username;

        $ssl     = !empty($this->config['use_ssl']);
        $port    = isset($this->config['ad_port']) ? (int) $this->config['ad_port'] : ($ssl ? 636 : 389);
        $timeout = isset($this->config['timeout']) ? (int) $this->config['timeout'] : 5;

        $reached = false;
        foreach ((array) $this->config['domain_controllers'] as $host) {
            $conn = @ldap_connect(($ssl ? "ldaps://" : "ldap://").$host.":".$port);
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

            $ok = @ldap_bind($conn, $bind_user, $password);
            $errno = ldap_errno($conn);
            @ldap_unbind($conn);

            if ($ok) {
                return true;
            }
            // 49 = invalid credentials, 50 = insufficient access, 53 = unwilling: server answered
            if (in_array($errno, [49, 50, 53], true)) {
                return false;
            }
            // -1 = can't contact server; anything else: try next controller
            if ($errno !== -1) {
                $reached = true;
            }
        }

        if ($reached) {
            return false;
        }
        throw new Exception(_("Cannot connect to directory server"));
    }
}
