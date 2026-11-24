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
        $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

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
     * @return string|null error message, null on success
     */
    public function login(string $username, string $password): ?string
    {
        $generic = 'Invalid username or password';
        $user = $this->loadUser($username);
        if ($user === null || $password === '') {
            usleep(random_int(300_000, 800_000));
            return $generic;
        }

        try {
            $ok = match ((string) $user['auth_method']) {
                'local' => $this->verifyLocal($user, $password),
                'ad'    => (new Ldap($this->config->ad))->authenticate($username, $password),
                default => false,
            };
        } catch (RuntimeException $e) {
            return 'Authentication error: ' . $e->getMessage();
        }
        if (!$ok) {
            usleep(random_int(300_000, 800_000));
            return $generic;
        }

        session_regenerate_id(true);
        $_SESSION = ['trapusername' => $user['username']];
        $this->user = $user;
        $this->db->run('UPDATE `users` SET `last_login` = NOW() WHERE `id` = ?', [(int) $user['id']]);
        return null;
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
