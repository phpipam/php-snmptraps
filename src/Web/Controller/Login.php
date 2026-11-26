<?php

declare(strict_types=1);

namespace Snmptraps\Web\Controller;

use JsonException;
use RuntimeException;
use Snmptraps\Auth;
use Snmptraps\Passkeys;
use Snmptraps\Totp;
use Snmptraps\Web\App;

/**
 * Sign-in (no authentication required):
 *   login/           username + password
 *   login/2fa/       TOTP code after the password, or the TOTP setup ($require_2fa)
 *   login/passkey/   GET: WebAuthn options (JSON), POST: verify the passkey (JSON)
 *   logout/
 */
final class Login
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(string $route, ?string $sub): void
    {
        if ($route === 'logout') {
            $this->app->auth->logout();
            $this->app->redirect('login/?loggedout=1');
        }
        match ($sub) {
            null, ''  => $this->password(),
            '2fa'     => $this->secondFactor(),
            'passkey' => $this->passkey(),
            default   => $this->app->redirect('login/'),
        };
    }

    /** 2FA / passkeys need the columns of migration 1.1.0 - until then the plain password login applies */
    private function ready(): bool
    {
        return $this->app->schema()->has('1.1.0');
    }

    private function password(): void
    {
        $auth = $this->app->auth;
        $error = null;
        $notice = null;

        if (isset($_GET['cancel'])) {
            $auth->clearSecondFactor();
        } elseif ($auth->secondFactor() !== null) {
            $this->app->redirect('login/2fa/');
        }
        if ($auth->isAuthenticated()) {
            $this->app->redirect('');
        }

        if ($this->app->isPost()) {
            [$user, $error] = $auth->checkPassword($this->app->input('trapusername'), (string) ($_POST['trappassword'] ?? ''));
            if ($user !== null) {
                if ($this->ready() && !empty($user['passwordless'])) {
                    $error = 'This account signs in with a passkey only.';
                } elseif ($this->ready() && (Auth::hasTotp($user) || $this->app->config->require2fa)) {
                    $auth->beginSecondFactor($user, !Auth::hasTotp($user));
                    $this->app->redirect('login/2fa/');
                } else {
                    $this->finish($auth->completeLogin($user, 'password'));
                }
            }
        } elseif (isset($_GET['loggedout'])) {
            $notice = 'You have logged out';
        } elseif (isset($_GET['expired'])) {
            $error = 'The sign-in has expired, please sign in again.';
        } elseif (isset($_GET['locked'])) {
            $error = 'Too many invalid codes, please sign in again.';
        }

        $this->render('partials/login-password', [
            'error'    => $error,
            'notice'   => $notice,
            'username' => $this->app->input('trapusername'),
            'passkeys' => $this->ready() && $this->passkeys()->unavailableReason() === null,
        ]);
    }

    private function secondFactor(): void
    {
        $auth = $this->app->auth;
        if ($auth->isAuthenticated()) {
            $this->app->redirect('');
        }
        $pending = $auth->secondFactor() ?? $this->app->redirect('login/?expired=1');
        $user = $pending['user'];
        // the user may have finished the setup in another tab meanwhile
        $setup = $pending['setup'] && !Auth::hasTotp($user);
        $secret = $pending['secret'];
        if ($setup && $secret === null) {
            $secret = Totp::newSecret();
            $auth->setSecondFactorSecret($secret);
        }

        $error = null;
        if ($this->app->isPost()) {
            $code = $this->app->input('code');
            if ($setup) {
                $step = Totp::verify((string) $secret, $code);
                if ($step !== null) {
                    $auth->setTotp((int) $user['id'], $secret, $step);
                    $this->finish($auth->completeLogin($user, 'password+totp'));
                }
            } elseif ($auth->verifyTotp($user, $code)) {
                $this->finish($auth->completeLogin($user, 'password+totp'));
            }
            if ($auth->secondFactorFailed()) {
                $this->app->redirect('login/?locked=1');
            }
            $error = 'Invalid code, please try again.';
        }

        $vars = ['error' => $error, 'user' => $user, 'setup' => $setup];
        if ($setup) {
            $uri = Totp::uri((string) $secret, (string) $user['username'], $this->app->config->title);
            $vars += ['secret' => (string) $secret, 'qr' => Totp::qrCode($uri), 'uri' => $uri];
        }
        $this->render('partials/login-totp', $vars);
    }

    /** JSON endpoint used by public/js/passkey.js */
    private function passkey(): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        $passkeys = $this->passkeys();
        try {
            if (!$this->ready()) {
                throw new RuntimeException('Passkeys are not available until the database migration 1.1.0 is applied');
            }
            if (($reason = $passkeys->unavailableReason()) !== null) {
                throw new RuntimeException($reason);
            }
            if (!$this->app->isPost()) {
                echo json_encode($passkeys->loginOptions(), JSON_THROW_ON_ERROR);
                return;
            }
            $user = $passkeys->authenticate(self::jsonBody());
            $returnTo = $this->app->auth->completeLogin($user, 'passkey');
            echo json_encode(['redirect' => $this->target($returnTo)], JSON_THROW_ON_ERROR);
        } catch (RuntimeException | JsonException $e) {
            usleep(random_int(200_000, 500_000));
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /** @return array<string,mixed> JSON request body */
    public static function jsonBody(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid request');
        }
        return $data;
    }

    private function passkeys(): Passkeys
    {
        return $this->app->passkeys();
    }

    /** only local paths below the application base */
    private function target(string $returnTo): string
    {
        $base = $this->app->config->base;
        return str_starts_with($returnTo, $base) && !str_contains($returnTo, '//') ? $returnTo : $base;
    }

    private function finish(string $returnTo): never
    {
        $this->app->redirect($this->target($returnTo));
    }

    private function render(string $partial, array $vars): void
    {
        echo $this->app->view->render('login', ['content' => $this->app->view->render($partial, $vars)]);
    }
}
