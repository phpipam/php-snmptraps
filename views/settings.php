<?php
/** @var Snmptraps\Web\App $app */
/** @var string $page */
/** @var array<string,string> $pages */
/** @var array<string,array>|null $fields */
/** @var list<array>|null $rows */
/** @var array|null $user  user details sub page (settings/users/{id}/) */
$user ??= null;

$icons = [
    'severity_definitions' => 'ti-adjustments',
    'exceptions'           => 'ti-volume-off',
    'maintenance'          => 'ti-tool',
    'mibs'                 => 'ti-file-code',
    'users'                => 'ti-users',
    'general'              => 'ti-info-circle',
];
$intro = [
    'severity_definitions' => 'The first definition (lowest id) whose OID is contained in a received trap OID sets its severity.',
    'exceptions'           => 'Matching traps are not stored and no notifications are sent. Hostname "all" matches every host; empty content matches every trap with the OID.',
    'maintenance'          => 'No notifications are sent for a host during its maintenance window. Traps are still stored.',
    'mibs'                 => 'MIB files available to the system. Notifications found in a MIB can be imported as severity definitions.',
    'users'                => 'Administrator: settings and users. Operator: define / ignore / delete traps. User: view traps and edit own notification options.',
    'general'              => 'General settings are configured in config.php on the server.',
][$page];

// maintenance: start + stop shown as one "Active from / to" column
[$rangeFrom, $rangeTo] = $page === 'maintenance' ? ['start', 'stop'] : [null, null];
$fmtDate = static fn (?string $d): string => $d ? substr($d, 0, 16) : '-';

$listValue = static function (mixed $v): string {
    $parts = array_filter(explode(';', (string) $v), static fn ($x) => $x !== '');
    $out = '';
    foreach ($parts as $x) {
        $out .= '<span class="badge badge-outline text-secondary me-1 mb-1">' . e($x) . '</span>';
    }
    return $out;
};
?>
<div class="row row-cards">
    <div class="col-12 col-md-3 col-xl-2">
        <div class="list-group">
            <?php foreach ($pages as $k => $label): ?>
            <a href="settings/<?= e($k) ?>/" class="list-group-item list-group-item-action d-flex align-items-center<?= $k === $page ? ' active' : '' ?>">
                <i class="ti <?= e($icons[$k]) ?> me-2"></i><?= e($label) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-12 col-md-9 col-xl-10">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="page-pretitle">Settings<?= $user !== null ? ' &middot; <a href="settings/users/" class="text-reset">' . e($pages[$page]) . '</a>' : '' ?></div>
                <h2 class="page-title"><?= $user !== null ? e($user['real_name'] ?: $user['username']) : e($pages[$page]) ?></h2>
                <?php if ($user === null): ?><p class="card-subtitle text-secondary mb-0 mt-1"><?= e($intro) ?></p><?php endif; ?>
            </div>
            <div class="card-actions d-flex gap-2">
                <?php if ($user !== null): ?>
                <a class="btn btn-sm load-modal" href="ajax/item/?table=users&amp;action=edit&amp;id=<?= (int) $user['id'] ?>"><i class="ti ti-pencil me-1"></i>Edit</a>
                <?php if ((int) $user['id'] !== $app->auth->userId()): ?>
                <a class="btn btn-sm btn-outline-danger load-modal" data-redirect="settings/users/" href="ajax/item/?table=users&amp;action=delete&amp;id=<?= (int) $user['id'] ?>"><i class="ti ti-trash me-1"></i>Delete</a>
                <?php endif; ?>
                <?php elseif (in_array($page, ['mibs', 'severity_definitions', 'exceptions', 'users', 'maintenance'], true)): ?>
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-filter"></i></span>
                    <input type="text" class="form-control form-control-sm table-filter" data-target=".table-settings" placeholder="Filter">
                </div>
                <?php endif; ?>
                <?php if (isset($fields) && $user === null): ?>
                <a class="btn btn-primary btn-sm load-modal" href="ajax/item/?table=<?= e($page) ?>&amp;action=add"><i class="ti ti-plus me-1"></i>Create new</a>
                <?php endif; ?>
            </div>
        </div>

<?php if ($page === 'general'):
    /** @var Snmptraps\Config $config */
    $nm = $config->notificationMethods;
    $items = [
        'Version'               => APP_VERSION,
        'Database schema'       => ($pendingMig = $app->schema()->pending()) === [] ? 'up to date (' . array_key_last(Snmptraps\Schema::MIGRATIONS) . ')' : 'migrations missing: ' . implode(", ", $pendingMig),
        'PHP version'           => PHP_VERSION,
        'Database'              => $config->db['user'] . '@' . $config->db['host'] . ':' . $config->db['port'] . '/' . $config->db['name'],
        'Web base path'         => $config->base,
        'Notification URL'      => $config->url !== '' ? $config->url : '(not set - links in notifications disabled)',
        'Notification methods'  => $nm === [] ? 'none' : implode(', ', $nm),
        'MIB directories'       => implode(', ', $config->mibDirectories),
        'Log file'              => $config->logFile !== false ? $config->logFile : '(disabled)',
        'Debug / raw trap log'  => ($config->writeDebugFile ? 'on' : 'off') . ' / ' . ($config->writeRawFile ? 'on' : 'off'),
        'PHP debugging'         => $config->debugging ? 'on' : 'off',
        'Strip hostname domain' => $config->stripHostnameDomain ? 'yes' : 'no',
        'Skip repeated notifications' => $config->notificationDedupMinutes > 0 ? "identical trap within {$config->notificationDedupMinutes} min" : 'off',
        'Repeat counter window' => $config->repeatWindowMinutes > 0 ? "{$config->repeatWindowMinutes} min" : 'off',
        'AD controllers'        => implode(', ', (array) ($config->ad['domain_controllers'] ?? [])) ?: '-',
    ]; ?>
        <div class="card-body">
            <div class="datagrid">
                <?php foreach ($items as $k => $v): ?>
                <div class="datagrid-item"><div class="datagrid-title"><?= e($k) ?></div><div class="datagrid-content"><?= e($v) ?></div></div>
                <?php endforeach; ?>
            </div>
            <p class="text-secondary small mt-3 mb-0">Passwords and API tokens are not shown.</p>
        </div>

<?php elseif ($page === 'mibs'):
    /** @var Snmptraps\Mib $mib */
    /** @var list<array{file:string,size:int}> $files */ ?>
        <div class="card-body py-2 text-secondary small">Directories <code><?= e(implode(', ', $mib->directories())) ?></code> &middot; <?= count($files) ?> files</div>
        <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover table-sm table-settings">
            <thead><tr><th>File</th><th style="width:120px">Size</th><th style="width:60px"></th></tr></thead>
            <tbody>
            <?php foreach ($files as $f): ?>
            <tr>
                <td><?= e($f['file']) ?></td>
                <td class="text-secondary"><?= e(number_format($f['size'] / 1024, 1)) ?> kB</td>
                <td class="text-end"><a class="btn btn-sm btn-ghost-primary load-modal" data-toggle="tooltip-nofollow" tippy-title="Create severity definitions" href="ajax/mib/?file=<?= e(rawurlencode($f['file'])) ?>"><i class="ti ti-file-import"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

<?php elseif ($user !== null):
    echo $app->view->render('partials/user-details', ['user' => $user, 'methods' => $methods, 'dashboard' => $dashboard, 'security' => $security]); ?>

<?php elseif ($page === 'users'):
    echo $app->view->render('partials/user-list', ['rows' => $rows]); ?>

<?php else: ?>
        <?php if ($rows === []): ?>
        <div class="card-body text-secondary">No records found.</div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover table-sm table-settings">
            <thead><tr>
                <?php foreach ($fields as $name => $f): if ($f['type'] === 'password' || ($f['list'] ?? true) === false || $name === $rangeTo) continue; ?>
                <th><?= e($name === $rangeFrom ? 'Active from / to' : $f['label']) ?></th>
                <?php endforeach; ?>
                <?php if ($page === 'maintenance'): ?><th>State</th><?php endif; ?>
                <th style="width:80px"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <?php foreach ($fields as $name => $f): if ($f['type'] === 'password' || ($f['list'] ?? true) === false || $name === $rangeTo) continue; ?>
                <?php if ($name === $rangeFrom): ?>
                <td class="text-secondary text-nowrap"><?= e($fmtDate($r[$rangeFrom] ?? null)) ?> &ndash; <?= e($fmtDate($r[$rangeTo] ?? null)) ?></td>
                <?php else: ?>
                <td class="<?= in_array($f['type'], ['textarea', 'datetime'], true) ? 'text-secondary' : '' ?>"><?= $f['type'] === 'checkboxes' ? $listValue($r[$name] ?? '') : e_nl((string) ($r[$name] ?? '')) ?></td>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($page === 'maintenance'):
                    $now = date('Y-m-d H:i:s');
                    [$c, $t] = $r['stop'] < $now ? ['secondary', 'Expired'] : ($r['start'] > $now ? ['azure', 'Scheduled'] : ['green', 'Active']); ?>
                <td><span class="badge bg-<?= $c ?>-lt"><?= $t ?></span></td>
                <?php endif; ?>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-ghost-secondary btn-icon load-modal" href="ajax/item/?table=<?= e($page) ?>&amp;action=edit&amp;id=<?= (int) $r['id'] ?>" data-toggle="tooltip-nofollow" tippy-title="Edit"><i class="ti ti-pencil"></i></a>
                    <a class="btn btn-sm btn-ghost-danger btn-icon load-modal" href="ajax/item/?table=<?= e($page) ?>&amp;action=delete&amp;id=<?= (int) $r['id'] ?>" data-toggle="tooltip-nofollow" tippy-title="Delete"><i class="ti ti-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
<?php endif; ?>
    </div>
    </div>
</div>
