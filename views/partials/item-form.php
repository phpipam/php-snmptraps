<?php
/** @var string $table */
/** @var string $action add|edit|delete */
/** @var int $id */
/** @var array<string,array> $fields */
/** @var array $row */
?>
<form id="modal-form">
<input type="hidden" name="table" value="<?= e($table) ?>">
<input type="hidden" name="action" value="<?= e($action) ?>">
<input type="hidden" name="id" value="<?= (int) $id ?>">

<?php if ($action === 'delete'): ?>
<p>Remove the following item?</p>
<table class="table table-striped table-noborder table-condensed">
    <?php foreach ($fields as $name => $f): if ($f['type'] === 'password') continue; ?>
    <tr><td><?= e($f['label']) ?></td><td><?= e_nl((string) ($row[$name] ?? '')) ?></td></tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
<table class="table table-striped table-noborder table-condensed">
<?php foreach ($fields as $name => $f):
    $val = (string) ($row[$name] ?? '');
    $req = !empty($f['required']) ? ' <span class="text-danger">*</span>' : '';
    $n   = e($name); ?>
<tr>
    <td style="width:170px;"><?= e($f['label']) . $req ?></td>
    <td>
    <?php switch ($f['type']):
        case 'select': ?>
        <select name="<?= $n ?>" class="form-control input-sm input-w-auto">
            <?php if (empty($f['required'])): ?><option value=""></option><?php endif; ?>
            <?php foreach ($f['options'] as $ov => $ol): ?>
            <option value="<?= e($ov) ?>"<?= (string) $ov === $val ? ' selected' : '' ?>><?= e($ol) ?></option>
            <?php endforeach; ?>
        </select>
        <?php break;
        case 'checkboxes':
            $selected = array_filter(explode(';', $val), static fn ($x) => $x !== '' && $x !== ($f['empty'] ?? ''));
            $options = $f['options'];
            if (!empty($f['keep_existing'])) {
                foreach ($selected as $s) { $options[$s] ??= $s; }
            } ?>
        <div style="max-height:200px;overflow-y:auto;">
            <?php foreach ($options as $ov => $ol): ?>
            <label style="font-weight:normal;display:block;margin:0;"><input type="checkbox" name="<?= $n ?>[]" value="<?= e($ov) ?>"<?= in_array((string) $ov, $selected, true) ? ' checked' : '' ?>> <?= e($ol) ?></label>
            <?php endforeach; ?>
            <?php if ($options === []): ?><span class="text-muted">none available</span><?php endif; ?>
        </div>
        <?php break;
        case 'textarea': ?>
        <textarea name="<?= $n ?>" class="form-control input-sm" rows="3"><?= e($val) ?></textarea>
        <?php break;
        case 'datetime': ?>
        <input type="datetime-local" step="1" name="<?= $n ?>" class="form-control input-sm input-w-auto" value="<?= e(str_replace(' ', 'T', $val)) ?>">
        <?php break;
        case 'time': ?>
        <input type="time" step="1" name="<?= $n ?>" class="form-control input-sm input-w-auto" value="<?= e($val) ?>">
        <?php break;
        case 'int': ?>
        <input type="number" name="<?= $n ?>" class="form-control input-sm input-w-auto" value="<?= e($val) ?>" min="<?= (int) ($f['min'] ?? 0) ?>" max="<?= (int) ($f['max'] ?? 0) ?>">
        <?php break;
        case 'password': ?>
        <input type="password" name="<?= $n ?>" class="form-control input-sm" autocomplete="new-password" value="">
        <?php break;
        default:
            $list = isset($f['datalist']) ? 'dl-' . $n : null; ?>
        <input type="text" name="<?= $n ?>" class="form-control input-sm" value="<?= e($val) ?>"<?= isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '' ?><?= $list ? ' list="' . $list . '"' : '' ?>>
        <?php if ($list): ?>
        <datalist id="<?= $list ?>"><?php foreach ($f['datalist'] as $o): ?><option value="<?= e($o) ?>"><?php endforeach; ?></datalist>
        <?php endif; ?>
    <?php endswitch; ?>
    <?php if (!empty($f['help'])): ?><span class="text-muted" style="font-size:11px;"><?= e($f['help']) ?></span><?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</form>
