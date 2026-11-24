<?php
/** @var Snmptraps\Web\App $app */
/** @var array<string, array{title:string,width:int,traps:list<array>}> $widgets */
use Snmptraps\Web\TrapPresenter;

$p = $app->presenter;
?>
<div class="container-fluid row" id="dashboard">
<?php foreach ($widgets as $group => $w): ?>
    <div class="col-xs-12 col-md-<?= (int) $w['width'] ?> widget-dash">
        <div class="inner">
            <h4><a href="severity/<?= e($group) ?>/"><?= e($w['title']) ?></a></h4>
            <div class="hContent">
                <table class="table snmp table-striped table-condensed table-hover">
                    <?= $p->headers(TrapPresenter::DASHBOARD_COLUMNS) ?>
                    <tbody><?= $p->rows($w['traps'], TrapPresenter::DASHBOARD_COLUMNS) ?></tbody>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
