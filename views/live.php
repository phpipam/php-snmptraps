<?php
/** @var Snmptraps\Web\App $app */
/** @var list<array> $traps */
use Snmptraps\Web\TrapPresenter;

$p = $app->presenter;
?>
<div class="container-fluid row">
    <h4>Live message update</h4>
    <hr>
    <div class="container pull-left">Messages will be updated every 15 seconds</div>
    <table class="table snmp live sorted table-noborder table-condensed table-hover" data-cookie-id-table="live">
        <?= $p->headers(TrapPresenter::LIST_COLUMNS, true) ?>
        <tbody><?= $p->rows($traps, TrapPresenter::LIST_COLUMNS, true) ?></tbody>
    </table>
</div>
<script>
$(function () {
    setInterval(function () {
        var $tbody = $('table.table.snmp.live tbody');
        var id = $tbody.find('tr[data-id]').first().attr('data-id') || 0;
        $('table.table.snmp tr').removeClass('new');
        $.get('ajax/live/', {id: id}, function (html) {
            if (html !== 'False') { $tbody.prepend(html); }
        });
    }, 15000);
});
</script>
