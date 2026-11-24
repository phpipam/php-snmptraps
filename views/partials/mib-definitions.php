<?php
/** @var string $file */
/** @var string $module */
/** @var list<array{oid:string,name:string,current:?string,comment:string}> $items */
use Snmptraps\Severity;
?>
<?php if ($items === []): ?>
<div class="text-secondary">No NOTIFICATION-TYPE / TRAP-TYPE definitions found in <?= e($file) ?>.</div>
<?php else: ?>
<p class="text-secondary">Module <strong><?= e($module) ?></strong>. Choose a severity for the notifications you want to define; "skip" leaves them unchanged. Existing traps with these OIDs are re-classified.</p>
<form id="modal-form">
<div class="table-responsive">
<table class="table table-sm table-vcenter">
    <thead><tr><th>Notification</th><th style="width:160px">Severity</th><th style="width:90px">Current</th><th>Comment</th></tr></thead>
    <tbody>
    <?php foreach ($items as $i => $it): ?>
    <tr>
        <td><?= e($it['name']) ?><input type="hidden" name="def[<?= $i ?>][oid]" value="<?= e($it['oid']) ?>"></td>
        <td>
            <select class="form-select form-select-sm" name="def[<?= $i ?>][severity]">
                <option value="">skip</option>
                <?php foreach (Severity::LEVELS as $s): ?>
                <option value="<?= e($s) ?>"<?= $s === $it['current'] ? ' selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="text-secondary"><?= e($it['current'] ?? 'new') ?></td>
        <td><input type="text" name="def[<?= $i ?>][comment]" class="form-control form-control-sm" value="<?= e($it['comment']) ?>"></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</form>
<?php endif; ?>
