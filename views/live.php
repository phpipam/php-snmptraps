<?php
/** @var Snmptraps\Web\App $app */
/** @var list<array> $traps */
use Snmptraps\Web\TrapPresenter;

$p = $app->presenter();
?>
<div class="row row-cards">
<div class="col-12">
<div class="card">
    <div class="card-header">
        <h3 class="card-title d-flex align-items-center"><span class="status-dot status-dot-animated bg-green d-block me-2"></span>Live traps</h3>
        <div class="card-actions text-secondary small">
            new traps are added every <span class="live-interval">15</span> s &middot; last check <span class="live-last">-</span>
            <a href="#" class="btn btn-sm btn-ghost-secondary ms-2 live-toggle" data-toggle="tooltip-nofollow" tippy-title="Pause / resume"><i class="ti ti-player-pause"></i></a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover table-traps table-live" data-live-url="ajax/live/" data-live-interval="15">
            <?= $p->headers(TrapPresenter::LIST) ?>
            <tbody><?= $p->rows($traps, TrapPresenter::LIST) ?></tbody>
        </table>
    </div>
</div>
</div>
</div>
