<?php

declare(strict_types=1);

namespace Snmptraps;

use RuntimeException;

/**
 * Session handling, authentication, roles, CSRF and per-user host restrictions.
 */
final class Auth
{
    public const ROLES = ['user', 'operator', 'administrator'];
    public const AUTH_METHODS = ['local', 'ad'];
    /** dashboard: max traps per severity widget, default layout (width:rows per group) */
    public const DASH_MAX_ROWS = 20;
    public const DASH_LAYOUT = '6:20;6:20;12:15;12:15';

    /** @var array<string,mixed>|null */
    private ?array $user = null;

    public function __construct(private readonly Config $config, private readonly Database $db)
    {
    }

    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = self::isHttps();

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name($this->config->sessionName);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $this->config->base,
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        if (!empty($_SESSION['trapusername'])) {
            $this->user = $this->loadUser((string) $_SESSION['trapusername']);
            if ($this->user === null) {
                $this->logout();
            }
        }
    }

    public static function isHttps(): bool
    {
        return (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    /** @return array<string,mixed> */
    public function user(): array
    {
        if ($this->user === null) {
            throw new RuntimeException('Not authenticated');
        }
        return $this->user;
    }

    public function userId(): int
    {
        return (int) $this->user()['id'];
    }

    public function isAdmin(): bool
    {
        return ($this->user['role'] ?? '') === 'administrator';
    }

    public function isOperator(): bool
    {
        return in_array($this->user['role'] ?? '', ['operator', 'administrator'], true);
    }

    /**
     * @return list<string>|null null means all hosts are permitted
     */
    public function permittedHosts(): ?array
    {
        if ($this->user === null) {
            return [];
        }
        $hosts = trim((string) ($this->user['hostnames'] ?? 'all'));
        if ($this->isAdmin() || $hosts === 'all' || $hosts === '') {
            return null;
        }
        return array_values(array_filter(explode(';', $hosts), static fn ($h) => $h !== ''));
    }

    public function canSeeHost(string $hostname): bool
    {
        $hosts = $this->permittedHosts();
        return $hosts === null || in_array($hostname, $hosts, true);
    }

    /**
     * First login step: username + password (local hash or AD bind). Does not log in.
     *
     * @return array{0: array<string,mixed>|null, 1: string|null} [user row, error message]
     */
    public function checkPassword(string $username, string $password): array
    {
        $generic = 'Invalid username or password';
        $user = $this->loadUser($username);
        if ($user === null || $password === '') {
            usleep(random_int(300_000, 800_000));
            return [null, $generic];
        }

        try {
            $ok = match ((string) $user['auth_method']) {
                'local' => $this->verifyLocal($user, $password),
                'ad'    => (new Ldap($this->config->ad))->authenticate($username, $password),
                default => false,
            };
        } catch (RuntimeException $e) {
            return [null, 'Authentication error: ' . $e->getMessage()];
        }
        if (!$ok) {
            usleep(random_int(300_000, 800_000));
            return [null, $generic];
        }
        return [$user, null];
    }

    /**
     * Logs the user in (new session id).
     *
     * @param string $method password | password+totp | passkey (kept in the session)
     * @return string the page that was requested before the login ("" = none)
     */
    public function completeLogin(array $user, string $method): string
    {
        $returnTo = (string) ($_SESSION['return_to'] ?? '');
        session_regenerate_id(true);
        $_SESSION = ['trapusername' => $user['username'], 'login_method' => $method];
        $this->user = $user;
        $this->db->run('UPDATE `users` SET `last_login` = NOW() WHERE `id` = ?', [(int) $user['id']]);
        return $returnTo;
    }

    // ---- second factor (TOTP) between the password step and the login

    /** pending second factor expires after (s) / allowed wrong codes */
    public const MFA_TTL = 600;
    public const MFA_TRIES = 5;

    /**
     * Password accepted, a TOTP code is needed next ($setup: the user has none yet and must
     * set it up first, $require_2fa). The user is not logged in until the code is verified.
     */
    public function beginSecondFactor(array $user, bool $setup): void
    {
        $returnTo = $_SESSION['return_to'] ?? null;
        session_regenerate_id(true);
        $_SESSION = ['mfa' => ['user' => (string) $user['username'], 'setup' => $setup, 'since' => time(), 'tries' => 0, 'secret' => null]];
        if (is_string($returnTo)) {
            $_SESSION['return_to'] = $returnTo;
        }
        $this->user = null;
    }

    /**
     * The pending second factor step, null when there is none or it expired.
     *
     * @return array{user: array<string,mixed>, setup: bool, secret: ?string}|null
     */
    public function secondFactor(): ?array
    {
        $m = $_SESSION['mfa'] ?? null;
        if (!is_array($m)) {
            return null;
        }
        $user = (int) ($m['since'] ?? 0) >= time() - self::MFA_TTL ? $this->loadUser((string) ($m['user'] ?? '')) : null;
        if ($user === null) {
            $this->clearSecondFactor();
            return null;
        }
        return ['user' => $user, 'setup' => (bool) $m['setup'], 'secret' => $m['secret'] ?? null];
    }

    /** secret shown during the setup at login (kept until the first valid code) */
    public function setSecondFactorSecret(string $secret): void
    {
        $_SESSION['mfa']['secret'] = $secret;
    }

    /** counts a wrong code; true = too many, the login is aborted */
    public function secondFactorFailed(): bool
    {
        $tries = (int) ($_SESSION['mfa']['tries'] ?? 0) + 1;
        $_SESSION['mfa']['tries'] = $tries;
        usleep(random_int(300_000, 800_000));
        if ($tries >= self::MFA_TRIES) {
            $this->clearSecondFactor();
            return true;
        }
        return false;
    }

    public function clearSecondFactor(): void
    {
        unset($_SESSION['mfa']);
    }

    /**
     * $require_2fa: a user signed in with a password only (session from before 2FA was required,
     * or the administrator reset the user's 2FA) is sent to the setup. Passkey sessions and
     * passwordless users are exempt.
     *
     * @param bool $ready migration 1.1.0 applied
     */
    public function enforceTwoFactor(bool $ready): void
    {
        if ($this->user === null || !$ready || !$this->config->require2fa || self::hasTotp($this->user)
            || !empty($this->user['passwordless']) || ($_SESSION['login_method'] ?? '') === 'passkey') {
            return;
        }
        $this->beginSecondFactor($this->user, true);
    }

    /** store / remove (null) the TOTP secret of a user */
    public function setTotp(int $userId, ?string $secret, ?int $step = null): void
    {
        $this->db->run('UPDATE `users` SET `totp_secret` = ?, `totp_last_step` = ? WHERE `id` = ?', [$secret, $secret === null ? null : $step, $userId]);
        if ($this->user !== null && (int) $this->user['id'] === $userId) {
            $this->user['totp_secret'] = $secret;
            $this->user['totp_last_step'] = $step;
        }
    }

    /**
     * Verifies a TOTP code of a user and marks its time step as used.
     */
    public function verifyTotp(array $user, string $code): bool
    {
        $secret = (string) ($user['totp_secret'] ?? '');
        $last = isset($user['totp_last_step']) ? (int) $user['totp_last_step'] : null;
        $step = Totp::verify($secret, $code, $last);
        if ($step === null) {
            return false;
        }
        // conditional update: the same code cannot be used twice in parallel requests
        return $this->db->run(
            'UPDATE `users` SET `totp_last_step` = ? WHERE `id` = ? AND (`totp_last_step` IS NULL OR `totp_last_step` < ?)',
            [$step, (int) $user['id'], $step],
        )->rowCount() === 1;
    }

    public static function hasTotp(array $user): bool
    {
        return trim((string) ($user['totp_secret'] ?? '')) !== '';
    }

    private function verifyLocal(array $user, string $password): bool
    {
        $hash = rtrim((string) ($user['password'] ?? ''));
        if ($hash === '' || !password_verify($password, $hash)) {
            return false;
        }
        // transparently upgrade legacy crypt()/SHA-512 hashes
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->db->run('UPDATE `users` SET `password` = ? WHERE `id` = ?', [self::hashPassword($password), (int) $user['id']]);
        }
        return true;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function logout(): void
    {
        $this->user = null;
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
            session_destroy();
        }
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function checkCsrf(?string $token): bool
    {
        return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Dashboard widget layout from users.dash_layout ("width:rows;width:rows;width:rows;width:rows").
     * A widget with 0 rows is hidden.
     *
     * @return array<string, array{width:int, rows:int}> keyed by severity group
     */
    public function dashLayout(): array
    {
        return self::parseDashLayout($this->user['dash_layout'] ?? null);
    }

    /**
     * "width:rows;..." for major;minor;informational;unknown -> enabled widgets
     *
     * @return array<string, array{width:int, rows:int}>
     */
    public static function parseDashLayout(?string $value): array
    {
        $groups = array_keys(Severity::GROUPS);
        $layout = [
            'major'         => ['width' => 6,  'rows' => self::DASH_MAX_ROWS],
            'minor'         => ['width' => 6,  'rows' => self::DASH_MAX_ROWS],
            'informational' => ['width' => 12, 'rows' => 15],
            'unknown'       => ['width' => 12, 'rows' => 15],
        ];
        $parts = explode(';', (string) $value);
        if (count($parts) === 4) {
            foreach ($parts as $i => $part) {
                $e = explode(':', $part);
                if (count($e) === 2 && ctype_digit($e[0]) && ctype_digit($e[1])) {
                    $layout[$groups[$i]] = ['width' => max(1, min(12, (int) $e[0])), 'rows' => min(self::DASH_MAX_ROWS, (int) $e[1])];
                }
            }
        }
        return array_filter($layout, static fn ($w) => $w['rows'] > 0);
    }

    /** @return array<string,mixed>|null */
    private function loadUser(string $username): ?array
    {
        return $this->db->one('SELECT * FROM `users` WHERE `username` = ? LIMIT 1', [$username]);
    }
}
