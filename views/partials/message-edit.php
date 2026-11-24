<?php
/** @var string $action define|ignore|delete */
/** @var array $trap */
/** @var list<string> $hosts */
use Snmptraps\Severity;

$intro = [
    'define' => 'Change the severity for this OID. All existing traps with this OID (and message, if set) will be updated.',
    'ignore' => 'Exclude this OID from processing. Matching traps are no longer stored or notified, and existing matching traps are deleted.',
    'delete' => 'Remove all stored traps for this OID. If content is set, only traps whose content contains it are removed.',
][$action];
?>
<p><?= e($intro) ?></p>
<form id="modal-form">
<input type="hidden" name="action" value="<?= e($action) ?>">
<input type="hidden" name="oid" value="<?= e($trap['oid']) ?>">
<table class="table table-noborder table-condensed">
<tr><td style="width:160px;">OID:</td><td><strong><?= e($trap['oid']) ?></strong></td></tr>

<?php if ($action === 'define'): ?>
<tr><td>Current severity:</td><td><strong><?= e($trap['severity']) ?></strong></td></tr>
<tr>
    <td>New severity:</td>
    <td>
        <select name="newseverity" class="form-control input-w-auto input-sm">
            <?php foreach (Severity::LEVELS as $s): ?>
            <option value="<?= e($s) ?>"<?= $s === $trap['severity'] ? ' selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
    </td>
</tr>
<tr>
    <td>Message</td>
    <td>
        <input type="text" class="form-control input-sm" name="content" maxlength="128" value="<?= e($trap['message']) ?>">
        <span class="text-muted">* Leave blank to match full OID</span>
    </td>
</tr>
<?php elseif ($action === 'ignore'): ?>
<tr>
    <td>Hostname:</td>
    <td>
        <select name="hostname" class="form-control input-w-auto input-sm">
            <option value="all">all</option>
            <?php foreach ($hosts as $h): ?>
            <option value="<?= e($h) ?>"<?= $h === $trap['hostname'] ? ' selected' : '' ?>><?= e($h) ?></option>
            <?php endforeach; ?>
        </select>
    </td>
</tr>
<tr>
    <td>Content</td>
    <td>
        <input type="text" class="form-control input-sm" name="content" maxlength="128" value="">
        <span class="text-muted">* Text the trap content must contain. Leave blank to match full OID.</span>
    </td>
</tr>
<?php else: ?>
<tr><td>Severity:</td><td><strong><?= e($trap['severity']) ?></strong></td></tr>
<tr>
    <td>Content</td>
    <td>
        <input type="text" class="form-control input-sm" name="content" maxlength="128" value="">
        <span class="text-muted">* Leave blank to delete all traps with this OID</span>
    </td>
</tr>
<?php endif; ?>

<?php if ($action !== 'delete'): ?>
<tr><td>Comment</td><td><input type="text" class="form-control input-sm" name="comment" maxlength="1000"></td></tr>
<?php endif; ?>
</table>
<?php if ($action === 'ignore' || $action === 'delete'): ?>
<details><summary class="text-muted">Trap content (for copy/paste)</summary><pre style="white-space:pre-wrap"><?= e($trap['content']) ?></pre></details>
<?php endif; ?>
</form>
