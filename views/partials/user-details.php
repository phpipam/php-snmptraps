<?php
/**
 * User details (settings/users/{id}/).
 *
 * @var Snmptraps\Web\App $app
 * @var array $user
 * @var list<string> $methods     notification methods enabled in config.php
 * @var array<string, array{width:int, rows:int}> $dashboard
 */
use Snmptraps\Severity;
use Snmptraps\Web\UserPresenter as U;

$sev = U::values($user['notification_severities']);
$hosts = U::values($user['hostnames']);

$section = static function (string $icon, string $title, array $items): string {
    $html = '<h4 class="subheader mt-4 mb-2"><i class="ti ' . e($icon) . ' me-1"></i>' . e($title) . '</h4>';
    foreach ($items as $label => $value) {
        $html .= '<div class="row py-1"><div class="col-12 col-sm-4 col-lg-3 text-secondary">' . e($label) . '</div><div class="col">' . $value . '</div></div>';
    }
    return $html;
};
$sevHtml = '';
foreach (Severity::all() as $s) {
    $on = in_array($s, $sev, true);
    $color = Severity::tablerColor($s);
    $sevHtml .= '<span class="badge badge-outline me-1 mb-1 ' . ($on ? "text-$color" : 'text-secondary opacity-50') . '">' . e($s) . '</span>';
}
$dashHtml = '';
foreach (Severity::GROUPS as $g => $def) {
    $w = $dashboard[$g] ?? null;
    $dashHtml .= '<span class="badge badge-outline me-1 mb-1 ' . ($w ? 'text-' . Severity::groupColor($g) : 'text-secondary opacity-50') . '">'
        . e($def['label']) . ($w ? ' &middot; ' . $w['rows'] . ' rows, width ' . $w['width'] . '/12' : ' &middot; hidden') . '</span>';
}
?>
<div class="card-body">
    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <?= $section('ti-user', 'Account', [
                'Username'       => '<strong>' . e($user['username']) . '</strong>',
                'Real name'      => e($user['real_name']) ?: '<span class="text-secondary">-</span>',
                'Role'           => U::role((string) $user['role']),
                'Authentication' => U::auth((string) $user['auth_method']),
                'Last login'     => $user['last_login'] ? e(date('d.m.Y H:i:s', (int) strtotime((string) $user['last_login']))) : '<span class="text-secondary">never</span>',
            ]) ?>
            <?= $section('ti-address-book', 'Contact', [
                'E-mail' => $user['email'] ? '<a href="mailto:' . e($user['email']) . '">' . e($user['email']) . '</a>' : '<span class="text-secondary">-</span>',
                'Phone'  => $user['tel'] ? e($user['tel']) : '<span class="text-secondary">-</span>',
            ]) ?>
            <?= $section('ti-layout-dashboard', 'Interface', [
                'Page reload' => (int) $user['reload_page'] > 0 ? (int) $user['reload_page'] . ' s' : '<span class="text-secondary">disabled</span>',
                'Dashboard'   => $dashHtml,
            ]) ?>
        </div>
        <div class="col-12 col-xl-6">
            <?= $section('ti-bell', 'Notifications', [
                'Methods'    => U::methods($user, $methods),
                'Severities' => $sevHtml,
                'Quiet time' => U::quietBadge($user) . '<div class="small text-secondary mt-1">No notifications are sent between start and stop (also across midnight).</div>',
            ]) ?>
            <?= $section('ti-server', 'Permitted hosts', []) ?>
            <?php if (U::allHosts($user)): ?>
            <div class="text-secondary">All hosts<?= $user['role'] === 'administrator' ? ' (administrator)' : '' ?></div>
            <?php else: ?>
            <div><?php foreach ($hosts as $h): ?><a href="host/<?= e(rawurlencode($h)) ?>/" class="badge badge-outline text-secondary me-1 mb-1"><?= e($h) ?></a><?php endforeach; ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
