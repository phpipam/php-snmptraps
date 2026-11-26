<?php
/**
 * Second sign-in step: TOTP code, or the TOTP setup when $require_2fa applies and the
 * user has none yet.
 *
 * @var Snmptraps\Web\App $app
 * @var string|null $error
 * @var array $user
 * @var bool $setup
 * @var string $secret  (setup)
 * @var string $qr      (setup) QR code data: URI
 * @var string $uri     (setup) otpauth:// URI
 */
use Snmptraps\Web\Controller\Security;
?>
<div class="card card-md">
    <div class="card-body">
        <form method="post" action="login/2fa/" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e($app->auth->csrfToken()) ?>">
            <?php if ($setup): ?>
            <h2 class="card-title mb-1"><i class="ti ti-shield-lock text-primary me-1"></i>Set up two-factor authentication</h2>
            <p class="text-secondary">Hello <?= e($user['real_name'] ?: $user['username']) ?>, signing in to <?= e($app->config->title) ?> requires a second factor.
                You need an authenticator app on your phone (e.g. Google Authenticator, Microsoft Authenticator, Authy, 1Password).</p>
            <?= $app->view->render('partials/totp-setup', ['secret' => $secret, 'qr' => $qr, 'uri' => $uri, 'current' => false]) ?>
            <div class="form-footer mt-3">
                <button type="submit" class="btn btn-primary w-100">Activate and sign in</button>
            </div>
            <?php else: ?>
            <h2 class="card-title mb-1"><i class="ti ti-shield-lock text-primary me-1"></i>Two-factor authentication</h2>
            <p class="text-secondary">Enter the 6-digit code from the authenticator app for <strong><?= e($user['username']) ?></strong>.</p>
            <?= Security::codeInput('code', 'Code') ?>
            <div class="form-footer mt-3">
                <button type="submit" class="btn btn-primary w-100">Verify</button>
            </div>
            <?php endif; ?>
            <?php if ($error !== null): ?><div class="alert alert-danger mt-3 mb-0"><?= e($error) ?></div><?php endif; ?>
        </form>
    </div>
    <div class="card-footer text-center">
        <a href="login/?cancel=1" class="text-secondary"><i class="ti ti-arrow-left me-1"></i>Sign in with another account</a>
        <?php if (!$setup): ?>
        <div class="small text-secondary mt-2">Lost your phone? Ask an administrator to reset your two-factor authentication.</div>
        <?php endif; ?>
    </div>
</div>
