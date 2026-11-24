<?php
/** @var Snmptraps\Web\App $app */
/** @var string $message */
/** @var list<array> $traps */
/** @var array|null $mib */
use Snmptraps\Web\TrapPresenter;

$p = $app->presenter;
$first = $traps[0] ?? null;
?>
<?php if ($first !== null): ?>
<h4>Details for message <strong><?= e($message) ?></strong></h4><hr>
<div class="container-fluid message-wrapper" style="padding:10px;">
<table class="table snmp table-noborder table-auto table-hover table-striped">
    <tr><td>Message</td><td><?= e($message) ?></td></tr>
    <tr><td>OID</td><td><?= e($first['oid']) ?></td></tr>
    <tr><td>Severity</td><td><?= $p->severity((string) $first['severity']) ?></td></tr>
    <tr><td>Trap objects</td><td><?= $mib && $mib['objects'] ? ' - ' . implode('<br> - ', array_map('e', $mib['objects'])) : '/' ?></td></tr>
    <tr><td>Trap description</td><td><?= $mib && $mib['description'] !== '' ? e_nl($mib['description']) : '/' ?></td></tr>
    <tr><td>MIB file</td><td><?= $mib ? e($mib['file']) : '/' ?></td></tr>
    <?php if ($app->auth->isOperator()): ?>
    <tr><td>Actions</td><td><?= $p->actions((int) $first['id']) ?></td></tr>
    <?php endif; ?>
</table>
</div>
<?php endif; ?>

<h4>Traps for message <strong><?= e($message) ?></strong></h4><hr>
<table class="table snmp sorted table-noborder table-condensed table-hover" data-cookie-id-table="message">
    <?= $p->headers(TrapPresenter::LIST_COLUMNS, true) ?>
    <tbody><?= $p->rows($traps, TrapPresenter::LIST_COLUMNS, true) ?></tbody>
</table>
