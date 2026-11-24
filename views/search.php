<?php
/** @var Snmptraps\Web\App $app */
/** @var list<string> $hosts */
/** @var array $form */
/** @var list<string> $errors */
/** @var list<array>|null $results */
use Snmptraps\Severity;
use Snmptraps\Web\TrapPresenter;

$sel = static fn (bool $c) => $c ? ' selected' : '';
?>
<div class="row row-cards">
<div class="col-12">
<form class="card" method="get" action="search/">
    <input type="hidden" name="search" value="1">
    <div class="card-header"><h3 class="card-title"><i class="ti ti-search me-2"></i>Search traps</h3></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <label class="form-label">Text</label>
                <input type="text" name="text" class="form-control" placeholder="Text in message or content" value="<?= e($form['text']) ?>" autofocus>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label">Host</label>
                <select name="hostname" class="form-select">
                    <option value="all">All hosts</option>
                    <?php foreach ($hosts as $h): ?>
                    <option value="<?= e($h) ?>"<?= $sel($h === $form['hostname']) ?>><?= e($h) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label">Severity</label>
                <select name="severity" class="form-select">
                    <option value="all">All severities</option>
                    <?php foreach (Severity::all() as $s): ?>
                    <option value="<?= e($s) ?>"<?= $sel($s === $form['severity']) ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label">From</label>
                <?= Snmptraps\Web\DateTimeField::dateTime('from', $form['from'], '00:00') ?>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label">To</label>
                <?= Snmptraps\Web\DateTimeField::dateTime('to', $form['to'], '23:59') ?>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label">Order</label>
                <select name="order" class="form-select">
                    <option value="desc"<?= $sel($form['order'] === 'desc') ?>>Newest first</option>
                    <option value="asc"<?= $sel($form['order'] === 'asc') ?>>Oldest first</option>
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label">Limit</label>
                <select name="limit" class="form-select">
                    <?php foreach ([50, 100, 250, 500] as $l): ?>
                    <option value="<?= $l ?>"<?= $sel($l === $form['limit']) ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <div class="card-footer text-end">
        <a href="search/" class="btn btn-ghost-secondary">Reset</a>
        <button type="submit" class="btn btn-primary"><i class="ti ti-search me-1"></i>Search</button>
    </div>
</form>
</div>

<?php if ($errors !== []): ?>
<div class="col-12">
    <div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div>
</div>
<?php endif; ?>

<?php if ($results !== null): ?>
<div class="col-12">
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Results</h3>
        <div class="card-actions"><span class="badge bg-transparent text-secondary"><?= count($results) ?><?= count($results) >= $form['limit'] ? '+ (limit reached)' : '' ?></span></div>
    </div>
    <?= $app->view->render('partials/trap-table', ['traps' => $results, 'columns' => TrapPresenter::LIST, 'empty' => 'No traps match the search.']) ?>
</div>
</div>
<?php endif; ?>
</div>
