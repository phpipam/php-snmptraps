<?php
/**
 * Authenticator app setup: QR code, secret for manual entry, first code.
 * Used by the sign-in setup (login/2fa/) and the Security page modal.
 *
 * @var string $secret
 * @var string $qr       QR code data: URI
 * @var string $uri      otpauth:// URI
 * @var bool   $current  replacing an existing setup: ask for a code of the current app too
 */
use Snmptraps\Totp;
use Snmptraps\Web\Controller\Security;
?>
<div class="row g-3 align-items-center mb-3">
    <div class="col-auto">
        <a href="<?= e($uri) ?>" class="d-block totp-qr" title="Open in an authenticator app on this device"><img src="<?= e($qr) ?>" width="180" height="180" alt="QR code for the authenticator app"></a>
    </div>
    <div class="col">
        <ol class="ps-3 mb-0">
            <li class="mb-1">Open the authenticator app and add an account.</li>
            <li class="mb-1">Scan the QR code, or enter this key manually (time based):
                <div class="mt-1"><code class="totp-secret user-select-all"><?= e(Totp::formatSecret($secret)) ?></code></div>
            </li>
            <li>Enter the 6-digit code the app shows.</li>
        </ol>
    </div>
</div>
<?php if ($current): ?>
<?= Security::codeInput('current_code', 'Code from your current authenticator app', false) ?>
<?php endif; ?>
<?= Security::codeInput('code', $current ? 'Code from the new authenticator app' : 'Code from the authenticator app', !$current) ?>
