<?php
/** @var Snmptraps\Web\App $app */
/** @var string $page */
/** @var array<string,string> $pages */
/** @var array<string,array>|null $fields */
/** @var list<array>|null $rows */

$intro = [
    'severity_definitions' => 'User-defined severities. The first definition (lowest id) whose OID is contained in a received trap OID sets its severity.',
    'exceptions'           => 'Traps matching these rules are not written to the database and no notifications are sent. Hostname "all" matches every host; empty content matches every trap with the OID.',
    'maintenance'          => 'Notifications are disabled for a host during its maintenance window. Traps are still received and stored.',
    'mibs'                 => 'MIB files available to the system. Notification definitions found in a MIB can be imported as severity definitions.',
    'users'                => "'Administrator' can manage settings and users, 'Operator' can define/ignore/delete traps, 'User' can only view traps and edit own notification options.",
    'general'              => 'General settings are configured in config.php on the server.',
][$page];

$listValue = static function (mixed $v): string {
    $parts = array_filter(explode(';', (string) $v), static fn ($x) => $x !== '');
    return $parts === [] ? '' : '&middot; ' . implode('<br>&middot; ', array_map('e', $parts));
};
?>
<h4>Settings</h4>
<hr style="margin-bottom:0;">
<div class="container-fluid">
<div class="row">
    <div class="col-xs-12 col-sm-3 col-md-2" style="padding-left:0;">
        <ul class="settings-nav">
            <?php foreach ($pages as $k => $label): ?>
            <li class="<?= $k === $page ? 'active' : '' ?>"><a href="settings/<?= e($k) ?>/"><?= e($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="col-xs-12 col-sm-9 col-md-10">
    <div class="container-fluid" style="padding:10px;">
        <h4 class="red"><?= e($pages[$page]) ?></h4>
        <p><?= e($intro) ?></p>

<?php if ($page === 'general'):
    /** @var Snmptraps\Config $config */
    $nm = $config->notificationMethods;
    $items = [
        'Version'               => APP_VERSION,
        'PHP version'           => PHP_VERSION,
        'Database'              => $config->db['user'] . '@' . $config->db['host'] . ':' . $config->db['port'] . '/' . $config->db['name'],
        'Web base path'         => $config->base,
        'Notification URL'      => $config->url !== '' ? $config->url : '(not set - links in notifications disabled)',
        'Notification methods'  => $nm === [] ? 'none' : implode(', ', $nm),
        'MIB directory'         => $config->mibDirectory,
        'Log file'              => $config->logFile !== false ? $config->logFile : '(disabled)',
        'Debug / raw trap log'  => ($config->writeDebugFile ? 'on' : 'off') . ' / ' . ($config->writeRawFile ? 'on' : 'off'),
        'PHP debugging'         => $config->debugging ? 'on' : 'off',
        'Strip hostname domain' => $config->stripHostnameDomain ? 'yes' : 'no',
        'AD controllers'        => implode(', ', (array) ($config->ad['domain_controllers'] ?? [])),
    ]; ?>
        <table class="table table-condensed table-noborder table-auto">
            <?php foreach ($items as $k => $v): ?>
            <tr><td><?= e($k) ?></td><td><strong><?= e($v) ?></strong></td></tr>
            <?php endforeach; ?>
        </table>
        <p class="text-muted">Passwords and API tokens are not shown.</p>

<?php elseif ($page === 'mibs'):
    /** @var Snmptraps\Mib $mib */
    /** @var list<array{file:string,size:int}> $files */ ?>
        <div class="alert alert-info">MIB directory: <?= e($mib->directory()) ?> (<?= count($files) ?> files)</div>
        <table class="table snmp sorted table-noborder table-condensed table-hover" data-cookie-id-table="settings_mib">
            <thead><tr><th data-field="file">File</th><th data-field="size">Size</th><th data-field="actions" class="disable-sorting"></th></tr></thead>
            <tbody>
            <?php foreach ($files as $f): ?>
            <tr>
                <td><?= e($f['file']) ?></td>
                <td><?= e(number_format($f['size'] / 1024, 1)) ?> kB</td>
                <td><a class="btn btn-success btn-xs pull-right load-modal-big" title="Create severity definitions" href="ajax/mib/?file=<?= e(rawurlencode($f['file'])) ?>"><i class="fa fa-plus"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

<?php else: ?>
        <a class="btn btn-success btn-sm load-modal" href="ajax/item/?table=<?= e($page) ?>&amp;action=add"><i class="fa fa-plus"></i> Create new</a>
        <table class="table snmp sorted table-noborder table-condensed table-hover" data-cookie-id-table="settings_<?= e($page) ?>">
            <thead><tr>
                <?php foreach ($fields as $name => $f): if ($f['type'] === 'password') continue; ?>
                <th data-field="<?= e($name) ?>"><?= e($f['label']) ?></th>
                <?php endforeach; ?>
                <?php if ($page === 'maintenance'): ?><th data-field="active">Active</th><?php endif; ?>
                <?php if ($page === 'users'): ?><th data-field="last_login">Last login</th><?php endif; ?>
                <th data-field="actions" class="disable-sorting"></th>
            </tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="<?= count($fields) + 2 ?>"><div class="alert alert-info">No records found</div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <?php foreach ($fields as $name => $f): if ($f['type'] === 'password') continue; ?>
                <td class="field-<?= e($name) ?>"><?= $f['type'] === 'checkboxes' ? $listValue($r[$name] ?? '') : e_nl((string) ($r[$name] ?? '')) ?></td>
                <?php endforeach; ?>
                <?php if ($page === 'maintenance'):
                    $now = date('Y-m-d H:i:s');
                    $state = $r['stop'] < $now ? ['danger', 'Expired'] : ($r['start'] > $now ? ['info', 'Scheduled'] : ['success', 'Active']); ?>
                <td><span class="badge alert alert-<?= $state[0] ?>" style="padding:0 5px;"><?= $state[1] ?></span></td>
                <?php endif; ?>
                <?php if ($page === 'users'): ?><td><?= e($r['last_login'] ?? '') ?></td><?php endif; ?>
                <td>
                    <div class="btn-group">
                        <a class="btn btn-default btn-xs load-modal" href="ajax/item/?table=<?= e($page) ?>&amp;action=edit&amp;id=<?= (int) $r['id'] ?>"><i class="fa fa-pencil"></i></a>
                        <a class="btn btn-default btn-xs load-modal" href="ajax/item/?table=<?= e($page) ?>&amp;action=delete&amp;id=<?= (int) $r['id'] ?>"><i class="fa fa-remove"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
<?php endif; ?>
    </div>
    </div>
</div>
</div>
