<?php
/**
 * User management list: one two-line row per user, details on settings/users/{id}/.
 *
 * @var Snmptraps\Web\App $app
 * @var list<array> $rows
 */
use Snmptraps\Notify\Notifier;
use Snmptraps\Severity;
use Snmptraps\Web\UserPresenter as U;

$enabled = Notifier::enabledMethods($app->config);
$sevCount = count(Severity::all());
?>
<?php if ($rows === []): ?>
<div class="card-body text-secondary">No users found.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-vcenter card-table table-hover table-settings table-users">
    <thead><tr>
        <th>User</th>
        <th>Access</th>
        <th>Contact</th>
        <th>Notifications</th>
        <th>Hosts</th>
        <th>Last login</th>
        <th style="width:110px"></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $id = (int) $r['id'];
        $quiet = U::quietTime($r);
        $sev = U::values($r['notification_severities']);
        $hosts = U::values($r['hostnames']); ?>
    <tr>
        <td>
            <div class="d-flex align-items-center">
                <span class="avatar avatar-sm me-2 bg-<?= $r['role'] === 'administrator' ? 'red' : ($r['role'] === 'operator' ? 'orange' : 'azure') ?>-lt"><?= e(mb_strtoupper(mb_substr((string) ($r['real_name'] ?: $r['username']), 0, 1))) ?></span>
                <div>
                    <a href="settings/users/<?= $id ?>/" class="fw-bold text-reset"><?= e($r['real_name'] ?: $r['username']) ?></a>
                    <div class="small text-secondary"><?= e($r['username']) ?><?= $id === $app->auth->userId() ? ' <span class="badge bg-transparent text-green p-0 ms-1">you</span>' : '' ?></div>
                </div>
            </div>
        </td>
        <td>
            <div><?= U::role((string) $r['role']) ?></div>
            <div class="small text-secondary mt-1"><?= U::auth((string) $r['auth_method']) ?></div>
        </td>
        <td>
            <div><?= $r['email'] ? '<i class="ti ti-mail text-secondary me-1"></i>' . e($r['email']) : '<span class="text-secondary">-</span>' ?></div>
            <div class="small text-secondary"><?= $r['tel'] ? '<i class="ti ti-phone me-1"></i>' . e($r['tel']) : '' ?></div>
        </td>
        <td>
            <div><?= U::methods($r, $enabled) ?></div>
            <div class="small text-secondary mt-1">
                <?= $sev === [] ? 'no severities' : (count($sev) === $sevCount ? 'all severities' : e(implode(', ', $sev))) ?>
                <?php if ($quiet !== null): ?><span class="ms-1 text-yellow text-nowrap"><i class="ti ti-moon"></i> <?= e($quiet) ?></span><?php endif; ?>
            </div>
        </td>
        <td>
            <?php if (U::allHosts($r)): ?>
            <span class="text-secondary">All hosts</span>
            <?php else: ?>
            <span data-toggle="tooltip" tippy-title="<?= e(implode('<br>', array_map('e', $hosts))) ?>"><?= count($hosts) ?> host<?= count($hosts) === 1 ? '' : 's' ?></span>
            <div class="small text-secondary text-truncate" style="max-width:180px"><?= e(implode(', ', $hosts)) ?></div>
            <?php endif; ?>
        </td>
        <td class="text-secondary text-nowrap">
            <?php if ($r['last_login']): ?>
            <div><?= e(date('d.m.Y', (int) strtotime((string) $r['last_login']))) ?></div>
            <div class="small"><?= e(date('H:i', (int) strtotime((string) $r['last_login']))) ?></div>
            <?php else: ?>never<?php endif; ?>
        </td>
        <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-ghost-secondary btn-icon" href="settings/users/<?= $id ?>/" data-toggle="tooltip-nofollow" tippy-title="Details"><i class="ti ti-eye"></i></a>
            <a class="btn btn-sm btn-ghost-secondary btn-icon load-modal" href="ajax/item/?table=users&amp;action=edit&amp;id=<?= $id ?>" data-toggle="tooltip-nofollow" tippy-title="Edit"><i class="ti ti-pencil"></i></a>
            <a class="btn btn-sm btn-ghost-danger btn-icon load-modal" href="ajax/item/?table=users&amp;action=delete&amp;id=<?= $id ?>" data-toggle="tooltip-nofollow" tippy-title="Delete"><i class="ti ti-trash"></i></a>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
