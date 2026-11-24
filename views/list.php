<?php
/**
 * Paginated trap list (severity group, host).
 *
 * @var Snmptraps\Web\App $app
 * @var string $title
 * @var string $color
 * @var string $base
 * @var list<array> $traps
 * @var int $total @var int $page @var int $pages @var int $limit @var string $q
 */
use Snmptraps\Web\Controller\Pages;
use Snmptraps\Web\TrapPresenter;
?>
<div class="row row-cards">
<div class="col-12">
<div class="card">
    <div class="card-header">
        <h3 class="card-title d-flex align-items-center"><span class="status-dot bg-<?= e($color) ?> d-block me-2"></span><?= e($title) ?></h3>
        <div class="card-actions">
            <form method="get" action="<?= e($base) ?>" class="d-flex gap-2 align-items-center">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-filter"></i></span>
                    <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Filter message, content, host, severity" style="min-width:280px">
                </div>
                <select name="limit" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                    <?php foreach (Pages::LIMITS as $l): ?>
                    <option value="<?= $l ?>"<?= $l === $limit ? ' selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($q !== ''): ?><a href="<?= e($base) ?>" class="btn btn-sm btn-ghost-secondary" data-toggle="tooltip-nofollow" tippy-title="Clear filter"><i class="ti ti-x"></i></a><?php endif; ?>
            </form>
        </div>
    </div>
    <?= $app->view->render('partials/trap-table', ['traps' => $traps, 'columns' => TrapPresenter::LIST, 'empty' => $q !== '' ? 'No traps match the filter.' : 'No traps found.']) ?>
    <?= $app->view->render('partials/pagination', compact('page', 'pages', 'total', 'limit', 'q', 'base')) ?>
</div>
</div>
</div>
