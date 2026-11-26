<?php
/**
 * The user's own sign-in security (security/): authenticator app (TOTP), passkeys, passwordless sign-in.
 *
 * @var Snmptraps\Web\App $app
 * @var bool $ready        migration 1.1.0 applied
 * @var array $user
 * @var bool $totp         authenticator app set up
 * @var bool $required     $require_2fa
 * @var bool $canDisable   2FA may be turned off
 * @var list<array> $passkeys
 * @var string|null $unavailable  why passkeys cannot be used here
 * @var string $method     how this session signed in
 */
$passwordless = !empty($user['passwordless']);
$fmt = static fn (?string $d): string => $d ? date('d.m.Y H:i', (int) strtotime($d)) : 'never';
$methodLabel = ['password' => 'password', 'password+totp' => 'password + authenticator code', 'passkey' => 'passkey'][$method] ?? null;
?>
<div class="page-header d-print-none mb-3">
    <div class="page-pretitle"><?= e($user['real_name'] ?: $user['username']) ?></div>
    <h2 class="page-title"><i class="ti ti-shield-lock me-2"></i>Sign-in security</h2>
    <?php if ($methodLabel !== null): ?><div class="text-secondary small mt-1">This session signed in with <?= e($methodLabel) ?>.</div><?php endif; ?>
</div>

<?php if (!$ready): ?>
<div class="alert alert-warning">Two-factor authentication and passkeys are available after the database migration 1.1.0 has been applied (<code>php bin/migrate.php</code>).</div>
<?php else: ?>
<div class="row row-cards">
    <div class="col-12 col-xl-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-device-mobile-message me-2"></i>Two-factor authentication</h3>
                <div class="card-actions">
                    <?php if ($totp): ?>
                    <span class="badge bg-green-lt"><i class="ti ti-check me-1"></i>Active</span>
                    <?php elseif ($passwordless): ?>
                    <span class="badge bg-secondary-lt">Not needed</span>
                    <?php else: ?>
                    <span class="badge bg-<?= $required ? 'red' : 'secondary' ?>-lt"><?= $required ? 'Required' : 'Off' ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <p class="text-secondary">When you sign in with your password you also enter a 6-digit code from an authenticator app on your phone
                    (Google Authenticator, Microsoft Authenticator, Authy, 1Password, ...). Signing in with a passkey does not ask for a code.</p>
                <?php if ($required && !$passwordless): ?>
                <p class="text-secondary small">The administrator requires two-factor authentication for sign-in with a password.</p>
                <?php endif; ?>
                <div class="btn-list">
                    <a href="ajax/totp/" class="btn btn-primary load-modal"><i class="ti ti-qrcode me-1"></i><?= $totp ? 'Replace authenticator app' : 'Set up authenticator app' ?></a>
                    <?php if ($totp && $canDisable): ?>
                    <a href="ajax/totp-disable/" class="btn btn-outline-danger load-modal"><i class="ti ti-shield-off me-1"></i>Turn off</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-fingerprint me-2"></i>Passkeys <span class="badge bg-transparent text-secondary ms-1"><?= count($passkeys) ?></span></h3>
                <div class="card-actions">
                    <?php if ($unavailable === null): ?>
                    <a href="ajax/passkey-add/" class="btn btn-primary btn-sm load-modal passkey-supported d-none"><i class="ti ti-plus me-1"></i>Add passkey</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <p class="text-secondary mb-0">Sign in with your fingerprint, face, device PIN or a security key instead of a password and code.</p>
                <?php if ($unavailable !== null): ?>
                <div class="alert alert-warning mt-3 mb-0"><i class="ti ti-alert-triangle me-1"></i><?= e($unavailable) ?></div>
                <?php else: ?>
                <div class="alert alert-warning mt-3 mb-0 passkey-unsupported d-none">This browser does not support passkeys.</div>
                <?php endif; ?>
            </div>
            <?php if ($passkeys !== []): ?>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Name</th><th>Added</th><th>Last used</th><th class="w-1"></th></tr></thead>
                    <tbody>
                    <?php foreach ($passkeys as $pk): ?>
                    <tr>
                        <td>
                            <i class="ti ti-key me-1 text-secondary"></i><?= e($pk['name']) ?>
                            <?php if ((int) $pk['synced'] === 1): ?><span class="badge bg-azure-lt ms-1" data-toggle="tooltip-nofollow" tippy-title="Synced passkey (password manager / cloud keychain)">synced</span><?php endif; ?>
                        </td>
                        <td class="text-secondary text-nowrap"><?= e($fmt($pk['created'])) ?></td>
                        <td class="text-secondary text-nowrap"><?= e($fmt($pk['last_used'])) ?></td>
                        <td><a href="ajax/passkey-delete/?id=<?= (int) $pk['id'] ?>" class="btn btn-sm btn-ghost-danger btn-icon load-modal" data-toggle="tooltip-nofollow" tippy-title="Remove"><i class="ti ti-trash"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <div class="card-footer">
                <div class="d-flex align-items-center">
                    <div>
                        <div class="fw-bold">Passkey-only sign-in <?= $passwordless ? '<span class="badge bg-orange-lt ms-1">On</span>' : '' ?></div>
                        <div class="text-secondary small"><?= $passwordless
                            ? 'Your password is not accepted - you sign in with a passkey only.'
                            : 'Turn off sign-in with a password, so that only your passkeys work (passwordless).' ?></div>
                    </div>
                    <div class="ms-auto ps-3">
                        <?php if ($passwordless || $passkeys !== []): ?>
                        <a href="ajax/passwordless/" class="btn btn-sm <?= $passwordless ? '' : 'btn-outline-warning' ?> load-modal text-nowrap"><?= $passwordless ? 'Allow password' : 'Turn on' ?></a>
                        <?php else: ?>
                        <span class="text-secondary small text-nowrap">add a passkey first</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
