<?php
/** @var string $action define|ignore|delete */
/** @var array $trap */
/** @var list<string> $hosts */
use Snmptraps\Severity;

$intro = [
    'define' => 'Change the severity for this OID. All existing traps with this OID (and message, if set) are updated.',
    'ignore' => 'Exclude this OID from processing. Matching traps are no longer stored or notified, and existing matching traps are deleted.',
    'delete' => 'Remove stored traps for this OID. If content is set, only traps whose content contains it are removed.',
][$action];
?>
<div class="alert alert-<?= $action === 'delete' ? 'danger' : 'info' ?> mb-3"><?= e($intro) ?></div>
<form id="modal-form" autocomplete="off">
<input type="hidden" name="action" value="<?= e($action) ?>">
<input type="hidden" name="oid" value="<?= e($trap['oid']) ?>">

<div class="mb-3 row">
    <label class="col-sm-4 col-form-label">OID</label>
    <div class="col"><div class="form-control-plaintext"><code><?= e($trap['oid']) ?></code></div></div>
</div>

<?php if ($action === 'define'): ?>
<div class="mb-3 row">
    <label class="col-sm-4 col-form-label">Current severity</label>
    <div class="col"><div class="form-control-plaintext"><?= e($trap['severity']) ?></div></div>
</div>
<div class="mb-3 row">
    <label class="col-sm-4 col-form-label required">New severity</label>
    <div class="col">
        <select name="newseverity" class="form-select">
            <?php foreach (Severity::LEVELS as $s): ?>
            <option value="<?= e($s) ?>"<?= $s === $trap['severity'] ? ' selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="mb-3 row">
    <label class="col-sm-4 col-form-label">Message</label>
    <div class="col">
        <input type="text" class="form-control" name="content" maxlength="128" value="<?= e($trap['message']) ?>">
        <small class="form-hint">Only re-classify stored traps with exactly this message. Leave blank for the whole OID.</small>
    </div>
</div>
<?php elseif ($action === 'ignore'): ?>
<div class="mb-3 row">
    <label class="col-sm-4 col-form-label required">Host</label>
    <div class="col">
        <select name="hostname" class="form-select">
            <option value="all">all hosts</option>
            <?php foreach ($hosts as $h): ?>
            <option value="<?= e($h) ?>"<?= $h === $trap['hostname'] ? ' selected' : '' ?>><?= e($h) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="mb-3 row">
    <label class="col-sm-4 col-form-label">Content</label>
    <div class="col">
        <input type="text" class="form-control" name="content" maxlength="128" value="">
        <small class="form-hint">Text the trap content must contain (e.g. a line from below). Leave blank to match the whole OID.</small>
    </div>
</div>
<?php else: ?>
<div class="mb-3 row">
    <label class="col-sm-4 col-form-label">Content</label>
    <div class="col">
        <input type="text" class="form-control" name="content" maxlength="128" value="">
        <small class="form-hint">Leave blank to delete all traps with this OID.</small>
    </div>
</div>
<?php endif; ?>

<?php if ($action !== 'delete'): ?>
<div class="mb-3 row">
    <label class="col-sm-4 col-form-label">Comment</label>
    <div class="col"><input type="text" class="form-control" name="comment" maxlength="1000"></div>
</div>
<?php endif; ?>
</form>
<?php if ($action !== 'define'): ?>
<div class="text-secondary small mb-1">Trap content</div>
<pre class="trap-pre mb-0"><?= e($trap['content']) ?></pre>
<?php endif; ?>
