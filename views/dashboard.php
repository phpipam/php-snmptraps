<?php
/** @var Snmptraps\Web\App $app */
/** @var array<string, array{title:string,width:int,traps:list<array>}> $widgets */
use Snmptraps\Severity;
use Snmptraps\Web\TrapPresenter;
?>
<div class="row row-deck row-cards">
<?php foreach ($widgets as $group => $w):
    $col = $w['width'] >= 12 ? 'col-12' : 'col-12 col-xl-' . (int) $w['width']; ?>
    <div class="<?= $col ?>">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title d-flex align-items-center">
                    <span class="status-dot status-dot-animated bg-<?= Severity::groupColor($group) ?> d-block me-2"></span>
                    <a href="severity/<?= e($group) ?>/" class="text-reset"><?= e($w['title']) ?></a>
                </h3>
                <div class="card-actions">
                    <span class="badge bg-transparent text-secondary"><?= count($w['traps']) ?> latest</span>
                </div>
            </div>
            <?= $app->view->render('partials/trap-table', ['traps' => $w['traps'], 'columns' => TrapPresenter::COMPACT, 'empty' => 'No traps.']) ?>
        </div>
    </div>
<?php endforeach; ?>
<?php if ($widgets === []): ?>
    <div class="col-12"><div class="card"><div class="card-body text-secondary">All dashboard widgets are disabled in your profile (dashboard layout).</div></div></div>
<?php endif; ?>
</div>
