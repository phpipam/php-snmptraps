<?php
/** @var Snmptraps\Web\App $app */
/** @var string $group */
/** @var string $title */
use Snmptraps\Web\TrapPresenter;
?>
<div class="container-fluid row">
    <h4><?= e($title) ?> severities</h4>
    <hr>
    <table class="table snmp sorted sorted-ajax table-noborder table-condensed table-hover" data-url="ajax/traps/?scope=severity&amp;type=<?= e(rawurlencode($group)) ?>">
        <?= $app->presenter->headers(TrapPresenter::LIST_COLUMNS, true) ?>
    </table>
</div>
