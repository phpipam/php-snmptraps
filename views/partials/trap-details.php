<?php
/** @var Snmptraps\Web\App $app */
/** @var array $trap */
/** @var array|null $mib */
use Snmptraps\Severity;

$p = $app->presenter;
$cells = $p->cells($trap, ['id', 'hostname', 'ip', 'date', 'message', 'oid', 'severity', 'content', 'raw'], true);
$rows = [
    'ID'         => $cells['id'],
    'Hostname'   => $cells['hostname'],
    'IP address' => $cells['ip'],
    'Date'       => $cells['date'],
    'Message'    => $cells['message'],
    'OID'        => $cells['oid'],
    'Severity'   => $cells['severity'],
    'Content'    => '<hr>' . $cells['content'],
    'Raw trap'   => '<hr>' . $cells['raw'] . '<hr>',
    'Trap description' => $mib !== null && $mib['description'] !== '' ? e_nl($mib['description']) . '<br><span class="text-muted">' . e($mib['file']) . '</span>' : '/',
];
if ($app->auth->isOperator()) {
    $rows['Actions'] = $p->actions((int) $trap['id']);
}
$cls = Severity::cssClass((string) $trap['severity']);
?>
<table class="table snmp table-noborder table-condensed table-hover">
<?php foreach ($rows as $label => $html): ?>
<tr class="<?= $cls ?>">
    <td style="vertical-align:middle !important;white-space:nowrap;"><?= e($label) ?></td>
    <td style="word-break:break-all;"><?= $html ?></td>
</tr>
<?php endforeach; ?>
</table>
