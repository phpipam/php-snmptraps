<?php

declare(strict_types=1);

namespace Snmptraps\Web\Controller;

use JsonException;
use RuntimeException;
use Snmptraps\Auth;
use Snmptraps\Totp;
use Snmptraps\Web\App;
use Snmptraps\Web\HttpException;
use Snmptraps\Web\View;

/**
 * Two-factor authentication (TOTP), passkeys and passwordless sign-in:
 *   security/                   the user's own settings (page)
 *   ajax/totp/                  set up / replace the authenticator app (modal)
 *   ajax/totp-disable/          turn 2FA off (modal)
 *   ajax/passkey-add/           name + register a passkey (modal, then passkey.js)
 *   ajax/passkey-options/       WebAuthn registration options (JSON)
 *   ajax/passkey-register/      verify + store the new passkey (JSON)
 *   ajax/passkey-delete/?id=    remove a passkey (modal)
 *   ajax/passwordless/          passkey-only sign-in on / off (modal)
 *   ajax/user-security/?id=&action=reset-totp|passwordless-off|delete-passkey&pk=|delete-passkeys
 *                               administrators, from the user details page (modal)
 */
final class Security
{
    public function __construct(private readonly App $app)
    {
    }

    private function requireReady(): void
    {
        if (!$this->app->schema()->has('1.1.0')) {
            throw new HttpException(503, 'Two-factor authentication and passkeys are not available until the database migration 1.1.0 is applied.');
        }
    }

    private function user(): array
    {
        return $this->app->auth->user();
    }

    private function userId(): int
    {
        return $this->app->auth->userId();
    }

    /** 2FA can be switched off unless $require_2fa applies (passwordless users never use it) */
    private function canDisableTotp(array $user): bool
    {
        return !$this->app->config->require2fa || !empty($user['passwordless']);
    }

    public function page(): string
    {
        $ready = $this->app->schema()->has('1.1.0');
        $user = $this->user();
        $passkeys = $this->app->passkeys();
        return $this->app->view->render('security', [
            'ready'       => $ready,
            'user'        => $user,
            'totp'        => $ready && Auth::hasTotp($user),
            'required'    => $this->app->config->require2fa,
            'canDisable'  => $this->canDisableTotp($user),
            'passkeys'    => $ready ? $passkeys->forUser((int) $user['id']) : [],
            'unavailable' => $passkeys->unavailableReason(),
            'method'      => (string) ($_SESSION['login_method'] ?? ''),
        ]);
    }

    // ---- TOTP

    public function totpForm(): string
    {
        $this->requireReady();
        $user = $this->user();
        $secret = Totp::newSecret();
        $_SESSION['totp_setup'] = ['secret' => $secret, 'time' => time()];
        $uri = Totp::uri($secret, (string) $user['username'], $this->app->config->title);
        $body = '<form id="modal-form" autocomplete="off">'
            . $this->app->view->render('partials/totp-setup', ['secret' => $secret, 'qr' => Totp::qrCode($uri), 'uri' => $uri, 'current' => Auth::hasTotp($user)])
            . '</form>';
        $title = Auth::hasTotp($user) ? 'Replace authenticator app' : 'Set up two-factor authentication';
        return (new Ajax($this->app))->modal($title, $body, 'Activate', 'ajax/totp/');
    }

    public function totpSubmit(): string
    {
        $this->requireReady();
        $user = $this->user();
        $setup = $_SESSION['totp_setup'] ?? null;
        if (!is_array($setup) || (int) $setup['time'] < time() - Auth::MFA_TTL) {
            return View::alert('danger', 'The setup has expired, please close this window and start again.');
        }
        if (Auth::hasTotp($user) && !$this->app->auth->verifyTotp($user, $this->app->input('current_code'))) {
            usleep(random_int(300_000, 800_000));
            return View::alert('danger', 'The code of your current authenticator app is invalid.');
        }
        $step = Totp::verify((string) $setup['secret'], $this->app->input('code'));
        if ($step === null) {
            usleep(random_int(300_000, 800_000));
            return View::alert('danger', 'Invalid code - check that the time on your phone is correct and try again.');
        }
        unset($_SESSION['totp_setup']);
        $this->app->auth->setTotp($this->userId(), (string) $setup['secret'], $step);
        return View::alert('success', 'Two-factor authentication is active. You will be asked for a code at your next sign-in with a password.');
    }

    public function totpDisableForm(): string
    {
        $this->requireReady();
        $user = $this->user();
        if (!Auth::hasTotp($user) || !$this->canDisableTotp($user)) {
            throw new HttpException(400, 'Two-factor authentication cannot be turned off.');
        }
        $body = '<form id="modal-form" autocomplete="off"><p>Sign-in with a password will no longer ask for a code.</p>'
            . self::codeInput('code', 'Current code from your authenticator app') . '</form>';
        return (new Ajax($this->app))->modal('Turn off two-factor authentication', $body, 'Turn off', 'ajax/totp-disable/', 'red');
    }

    public function totpDisableSubmit(): string
    {
        $this->requireReady();
        $user = $this->user();
        if (!Auth::hasTotp($user) || !$this->canDisableTotp($user)) {
            return View::alert('danger', 'Two-factor authentication cannot be turned off.');
        }
        if (!$this->app->auth->verifyTotp($user, $this->app->input('code'))) {
            usleep(random_int(300_000, 800_000));
            return View::alert('danger', 'Invalid code');
        }
        $this->app->auth->setTotp($this->userId(), null);
        return View::alert('success', 'Two-factor authentication turned off');
    }

    // ---- passkeys

    public function passkeyAddForm(): string
    {
        $this->requireReady();
        if (($reason = $this->app->passkeys()->unavailableReason()) !== null) {
            throw new HttpException(400, e($reason));
        }
        $body = $this->app->view->render('partials/passkey-add', []);
        return (new Ajax($this->app))->modal('Add a passkey', $body);
    }

    /** JSON: options for navigator.credentials.create() */
    public function passkeyOptions(): string
    {
        return $this->json(function (): array|object {
            $this->requireReady();
            return $this->app->passkeys()->registrationOptions($this->user());
        });
    }

    /** JSON: {name, credential} from passkey.js */
    public function passkeyRegister(): string
    {
        return $this->json(function (): array {
            $this->requireReady();
            $data = Login::jsonBody();
            $credential = is_array($data['credential'] ?? null) ? $data['credential'] : [];
            $this->app->passkeys()->register($this->user(), is_string($data['name'] ?? null) ? $data['name'] : '', $credential);
            return ['ok' => true];
        });
    }

    public function passkeyDeleteForm(): string
    {
        $this->requireReady();
        $pk = $this->ownPasskey((int) $this->app->input('id'));
        $last = count($this->app->passkeys()->forUser($this->userId())) === 1;
        $body = '<form id="modal-form"><input type="hidden" name="id" value="' . (int) $pk['id'] . '">'
            . '<p>Remove the passkey <strong>' . e($pk['name']) . '</strong>? It can no longer be used to sign in.</p>'
            . '<p class="text-secondary small mb-0">The passkey itself stays on your device or in your password manager - delete it there as well.</p>'
            . ($last && !empty($this->user()['passwordless']) ? View::alert('warning', 'This is your last passkey: passwordless sign-in is turned off, you will sign in with your password again.') : '')
            . '</form>';
        return (new Ajax($this->app))->modal('Remove passkey', $body, 'Remove', 'ajax/passkey-delete/', 'red');
    }

    public function passkeyDeleteSubmit(): string
    {
        $this->requireReady();
        $pk = $this->ownPasskey((int) $this->app->input('id'));
        $this->app->passkeys()->delete($this->userId(), (int) $pk['id']);
        return View::alert('success', 'Passkey removed');
    }

    private function ownPasskey(int $id): array
    {
        foreach ($this->app->passkeys()->forUser($this->userId()) as $pk) {
            if ((int) $pk['id'] === $id) {
                return $pk;
            }
        }
        throw new HttpException(404, 'Passkey not found');
    }

    // ---- passwordless

    public function passwordlessForm(): string
    {
        $this->requireReady();
        $user = $this->user();
        $on = !empty($user['passwordless']);
        if (!$on && ($error = $this->passwordlessBlocker($this->userId())) !== null) {
            return (new Ajax($this->app))->modal('Passwordless sign-in', View::alert('warning', e($error)));
        }
        $body = '<form id="modal-form"><input type="hidden" name="enable" value="' . ($on ? '0' : '1') . '">'
            . ($on
                ? '<p>Sign-in with your password will be possible again' . ($this->app->config->require2fa || Auth::hasTotp($user) ? ', together with a code from your authenticator app' : '') . '.</p>'
                : '<p>Your password will no longer be accepted - you sign in with one of your passkeys only.</p>'
                  . '<p class="text-secondary small mb-0">If you lose all your passkeys, an administrator has to turn passwordless sign-in off for you.</p>')
            . '</form>';
        return (new Ajax($this->app))->modal($on ? 'Allow password sign-in' : 'Passkey-only sign-in', $body, $on ? 'Allow password' : 'Turn on', 'ajax/passwordless/', $on ? 'azure' : 'orange');
    }

    public function passwordlessSubmit(): string
    {
        $this->requireReady();
        $enable = $this->app->input('enable') === '1';
        if ($enable && ($error = $this->passwordlessBlocker($this->userId())) !== null) {
            return View::alert('danger', e($error));
        }
        $this->app->db->run('UPDATE `users` SET `passwordless` = ? WHERE `id` = ?', [$enable ? 1 : 0, $this->userId()]);
        return View::alert('success', $enable ? 'Passwordless sign-in turned on' : 'Password sign-in allowed again');
    }

    /** reason why passwordless sign-in cannot be turned on yet (lock-out protection) */
    private function passwordlessBlocker(int $userId, bool $own = true): ?string
    {
        $all = $this->app->passkeys()->forUser($userId);
        if ($all === []) {
            return $own ? 'Add a passkey first.' : 'The user has no passkey.';
        }
        if (array_filter($all, static fn (array $p): bool => $p['last_used'] !== null) === []) {
            return $own
                ? 'Sign in with one of your passkeys once (log out, then "Sign in with a passkey") before turning off the password.'
                : 'The user has not signed in with a passkey yet.';
        }
        return null;
    }

    // ---- administrators: other users

    private const ADMIN_ACTIONS = [
        'reset-totp'       => ['Reset two-factor authentication', 'Reset 2FA', 'The user has to set up the authenticator app again (at the next sign-in if 2FA is required, otherwise under Security).'],
        'passwordless-off' => ['Allow password sign-in', 'Allow password', 'The user can sign in with the password again (and a 2FA code, if set up or required).'],
        'passwordless-on'  => ['Passkey-only sign-in', 'Turn on', 'The user\'s password is no longer accepted - the user signs in with a passkey only.'],
        'delete-passkey'   => ['Remove passkey', 'Remove', 'The passkey can no longer be used to sign in.'],
        'delete-passkeys'  => ['Remove all passkeys', 'Remove all', 'None of the user\'s passkeys can be used to sign in any more; passwordless sign-in is turned off.'],
    ];

    public function adminForm(): string
    {
        $this->app->requireAdmin();
        $this->requireReady();
        [$user, $action, $pk] = $this->adminTarget();
        [$title, $button, $text] = self::ADMIN_ACTIONS[$action];
        if ($action === 'passwordless-on' && ($error = $this->passwordlessBlocker((int) $user['id'], false)) !== null) {
            return (new Ajax($this->app))->modal($title, View::alert('warning', e($error)));
        }
        $body = '<form id="modal-form"><input type="hidden" name="id" value="' . (int) $user['id'] . '">'
            . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="pk" value="' . (int) ($pk['id'] ?? 0) . '">'
            . '<p><strong>' . e($user['real_name'] ?: $user['username']) . '</strong>' . ($pk !== null ? ' &middot; passkey <strong>' . e($pk['name']) . '</strong>' : '') . '</p>'
            . '<p class="mb-0">' . e($text) . '</p></form>';
        return (new Ajax($this->app))->modal($title, $body, $button, 'ajax/user-security/', 'red');
    }

    public function adminSubmit(): string
    {
        $this->app->requireAdmin();
        $this->requireReady();
        [$user, $action, $pk] = $this->adminTarget();
        $id = (int) $user['id'];
        if ($action === 'passwordless-on' && ($error = $this->passwordlessBlocker($id, false)) !== null) {
            return View::alert('danger', e($error));
        }
        $passkeys = $this->app->passkeys();
        match ($action) {
            'reset-totp'       => $this->app->auth->setTotp($id, null),
            'passwordless-off' => $this->app->db->run('UPDATE `users` SET `passwordless` = 0 WHERE `id` = ?', [$id]),
            'passwordless-on'  => $this->app->db->run('UPDATE `users` SET `passwordless` = 1 WHERE `id` = ?', [$id]),
            'delete-passkey'   => $passkeys->delete($id, (int) $pk['id']),
            'delete-passkeys'  => $passkeys->deleteAll($id),
        };
        return View::alert('success', e(self::ADMIN_ACTIONS[$action][0]) . ': done');
    }

    /** @return array{0: array, 1: string, 2: ?array} user, action, passkey */
    private function adminTarget(): array
    {
        $user = $this->app->db->find('users', (int) $this->app->input('id')) ?? throw new HttpException(404, 'User not found');
        $action = $this->app->input('action');
        if (!isset(self::ADMIN_ACTIONS[$action])) {
            throw new HttpException(400, 'Invalid action');
        }
        $pk = null;
        if ($action === 'delete-passkey') {
            $pkId = (int) $this->app->input('pk');
            foreach ($this->app->passkeys()->forUser((int) $user['id']) as $p) {
                if ((int) $p['id'] === $pkId) {
                    $pk = $p;
                }
            }
            $pk ?? throw new HttpException(404, 'Passkey not found');
        }
        return [$user, $action, $pk];
    }

    // ---- helpers

    /** numeric one-time code input */
    public static function codeInput(string $name, string $label, bool $autofocus = true): string
    {
        return '<div class="mb-2"><label class="form-label">' . e($label) . '</label>'
            . '<input type="text" name="' . e($name) . '" class="form-control form-control-lg totp-code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7"'
            . ' autocomplete="one-time-code" placeholder="000000" required' . ($autofocus ? ' autofocus' : '') . '></div>';
    }

    /** runs $fn and returns its result as JSON, errors as {"error": "..."} */
    private function json(callable $fn): string
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        try {
            return json_encode($fn(), JSON_THROW_ON_ERROR);
        } catch (HttpException $e) {
            return (string) json_encode(['error' => strip_tags($e->getMessage())]);
        } catch (RuntimeException | JsonException $e) {
            return (string) json_encode(['error' => $e->getMessage()]);
        }
    }
}
