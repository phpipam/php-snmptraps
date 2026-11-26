<?php
/**
 * @var Snmptraps\Web\App $app
 * @var string|null $error
 * @var string|null $notice
 * @var string $username
 * @var bool $passkeys  passkey sign-in possible (migration applied, HTTPS + host name)
 */
?>
<div class="card card-md">
    <div class="card-body">
        <form method="post" action="login/" autocomplete="off" name="login">
            <input type="hidden" name="csrf_token" value="<?= e($app->auth->csrfToken()) ?>">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" name="trapusername" value="<?= e($username) ?>" placeholder="Username" autocomplete="username" autocapitalize="off" spellcheck="false" autofocus required>
            </div>
            <div class="mb-2">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="trappassword" placeholder="Your password" autocomplete="current-password" required>
            </div>
            <div class="form-footer">
                <button type="submit" class="btn btn-primary w-100">Sign in</button>
            </div>
            <?php if ($error !== null): ?><div class="alert alert-danger mt-3 mb-0"><?= e($error) ?></div><?php endif; ?>
            <?php if ($notice !== null): ?><div class="alert alert-success mt-3 mb-0"><?= e($notice) ?></div><?php endif; ?>
        </form>
    </div>
    <?php if ($passkeys): ?>
    <div class="hr-text my-0 passkey-supported d-none">or</div>
    <div class="card-body passkey-supported d-none">
        <button type="button" class="btn w-100 passkey-login" data-url="login/passkey/"><i class="ti ti-fingerprint me-2"></i>Sign in with a passkey</button>
        <div class="alert alert-danger mt-3 mb-0 passkey-error d-none"></div>
    </div>
    <?php endif; ?>
    <div class="hr-text">info</div>
    <div class="card-body text-secondary d-flex justify-content-center text-center">
        Use your domain account or the local account provided by the administrator.
    </div>
</div>
