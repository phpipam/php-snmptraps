<?php
/** @var string $file */
/** @var string $module */
/** @var list<array{oid:string,name:string,current:?string,comment:string}> $items */
use Snmptraps\Severity;
?>
<?php if ($items === []): ?>
<div class="alert alert-info">No NOTIFICATION-TYPE / TRAP-TYPE definitions found in <?= e($file) ?>.</div>
<?php else: ?>
<p>Module <strong><?= e($module) ?></strong>. Select a severity for notifications you want to define; "skip" leaves them unchanged. Existing traps with these OIDs will be re-classified.</p>
<form id="modal-form">
<table class="table table-striped table-noborder table-condensed">
    <tr><th>Notification</th><th>Severity</th><th>Current</th><th>Comment</th></tr>
    <?php foreach ($items as $i => $it): ?>
    <tr>
        <td><?= e($it['name']) ?><input type="hidden" name="def[<?= $i ?>][oid]" value="<?= e($it['oid']) ?>"></td>
        <td>
            <select class="form-control input-sm" name="def[<?= $i ?>][severity]">
                <option value="">skip</option>
                <?php foreach (Severity::LEVELS as $s): ?>
                <option value="<?= e($s) ?>"<?= $s === $it['current'] ? ' selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="text-center"><?= e($it['current'] ?? 'new') ?></td>
        <td><input type="text" name="def[<?= $i ?>][comment]" class="form-control input-sm" value="<?= e($it['comment']) ?>"></td>
    </tr>
    <?php endforeach; ?>
</table>
</form>
<?php endif; ?>
