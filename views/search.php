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
<h4>Search messages</h4><hr>

<div class="container-fluid search-wrapper" style="padding:10px;">
<form name="search" id="search" method="get" action="search/">
<input type="hidden" name="search" value="1">
<table class="table table-condensed table-noborder table-auto">
<tr>
    <td>Hostname</td>
    <td>
        <select name="hostname" class="form-control input-sm input-w-auto">
            <option value="all">All</option>
            <?php foreach ($hosts as $h): ?>
            <option value="<?= e($h) ?>"<?= $sel($h === $form['hostname']) ?>><?= e($h) ?></option>
            <?php endforeach; ?>
        </select>
    </td>
    <td><span class="muted">Select hostname</span></td>
</tr>
<tr>
    <td>Severity</td>
    <td>
        <select name="severity" class="form-control input-sm input-w-auto">
            <option value="all">All</option>
            <?php foreach (Severity::all() as $s): ?>
            <option value="<?= e($s) ?>"<?= $sel($s === $form['severity']) ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
    </td>
    <td><span class="muted">Select severity</span></td>
</tr>
<tr>
    <td>From time</td>
    <td><input type="datetime-local" step="1" class="form-control input-sm input-w-auto" name="from" value="<?= e(str_replace(' ', 'T', $form['from'])) ?>"></td>
    <td><span class="muted">Optional start date</span></td>
</tr>
<tr>
    <td>To time</td>
    <td><input type="datetime-local" step="1" class="form-control input-sm input-w-auto" name="to" value="<?= e(str_replace(' ', 'T', $form['to'])) ?>"></td>
    <td><span class="muted">Optional end date</span></td>
</tr>
<tr>
    <td>Message</td>
    <td><input type="text" name="text" class="form-control input-sm" style="width:250px;" placeholder="Text to search for" value="<?= e($form['text']) ?>"></td>
    <td><span class="muted">Searches message and content</span></td>
</tr>
<tr>
    <td>Order</td>
    <td>
        <select name="order" class="form-control input-sm input-w-auto">
            <option value="desc"<?= $sel($form['order'] === 'desc') ?>>Descending</option>
            <option value="asc"<?= $sel($form['order'] === 'asc') ?>>Ascending</option>
        </select>
    </td>
    <td><span class="muted">Descending shows newest first</span></td>
</tr>
<tr>
    <td>Limit</td>
    <td>
        <select name="limit" class="form-control input-sm input-w-auto">
            <?php foreach ([500, 250, 100, 50] as $l): ?>
            <option value="<?= $l ?>"<?= $sel($l === $form['limit']) ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
    </td>
    <td><span class="muted">Number of results to display</span></td>
</tr>
<tr><td colspan="3"><hr></td></tr>
<tr>
    <td></td>
    <td class="text-right"><button type="submit" class="btn btn-sm btn-success"><i class="fa fa-search"></i> Search</button></td>
    <td></td>
</tr>
</table>
</form>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($results !== null): ?>
<h4>Search results:</h4><hr>
<table class="table snmp sorted table-noborder table-condensed table-hover" data-cookie-id-table="search">
    <?= $app->presenter->headers(TrapPresenter::LIST_COLUMNS, true) ?>
    <tbody><?= $app->presenter->rows($results, TrapPresenter::LIST_COLUMNS, true) ?></tbody>
</table>
<?php endif; ?>
