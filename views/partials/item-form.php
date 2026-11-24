<?php
/** @var string $table */
/** @var string $action add|edit|delete */
/** @var int $id */
/** @var array<string,array> $fields */
/** @var array $row */
?>
<form id="modal-form" autocomplete="off">
<input type="hidden" name="table" value="<?= e($table) ?>">
<input type="hidden" name="action" value="<?= e($action) ?>">
<input type="hidden" name="id" value="<?= (int) $id ?>">

<?php if ($action === 'delete'): ?>
<p>Remove the following item?</p>
<div class="datagrid">
    <?php foreach ($fields as $name => $f): if ($f['type'] === 'password') continue; ?>
    <div class="datagrid-item"><div class="datagrid-title"><?= e($f['label']) ?></div><div class="datagrid-content"><?= e_nl((string) ($row[$name] ?? '')) ?: '-' ?></div></div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<?php foreach ($fields as $name => $f):
    $val = (string) ($row[$name] ?? '');
    $n   = e($name); ?>
<div class="mb-3 row">
    <label class="col-12 col-sm-4 col-form-label<?= !empty($f['required']) ? ' required' : '' ?>"><?= e($f['label']) ?></label>
    <div class="col">
    <?php switch ($f['type']):
        case 'select': ?>
        <select name="<?= $n ?>" class="form-select">
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
            }
            $many = count($options) > 12; ?>
        <?php if ($many): ?><input type="text" class="form-control form-control-sm mb-2 checkbox-filter" placeholder="Filter"><?php endif; ?>
        <div class="<?= $many ? 'checkbox-scroll' : '' ?>">
            <?php foreach ($options as $ov => $ol): ?>
            <label class="form-check<?= $many ? '' : ' form-check-inline' ?> mb-1"><input class="form-check-input" type="checkbox" name="<?= $n ?>[]" value="<?= e($ov) ?>"<?= in_array((string) $ov, $selected, true) ? ' checked' : '' ?>><span class="form-check-label"><?= e($ol) ?></span></label>
            <?php endforeach; ?>
            <?php if ($options === []): ?><span class="text-secondary">none available</span><?php endif; ?>
        </div>
        <?php break;
        case 'textarea': ?>
        <textarea name="<?= $n ?>" class="form-control" rows="2"><?= e($val) ?></textarea>
        <?php break;
        case 'datetime': ?>
        <?= Snmptraps\Web\DateTimeField::dateTime($name, $val) ?>
        <?php break;
        case 'time': ?>
        <div style="max-width:9rem"><?= Snmptraps\Web\DateTimeField::time($name, $val) ?></div>
        <?php break;
        case 'int': ?>
        <input type="number" name="<?= $n ?>" class="form-control w-auto" value="<?= e($val) ?>" min="<?= (int) ($f['min'] ?? 0) ?>" max="<?= (int) ($f['max'] ?? 0) ?>">
        <?php break;
        case 'password': ?>
        <input type="password" name="<?= $n ?>" class="form-control" autocomplete="new-password" value="">
        <?php break;
        default:
            $list = isset($f['datalist']) ? 'dl-' . $n : null; ?>
        <input type="text" name="<?= $n ?>" class="form-control" value="<?= e($val) ?>"<?= isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '' ?><?= $list ? ' list="' . $list . '"' : '' ?>>
        <?php if ($list): ?>
        <datalist id="<?= $list ?>"><?php foreach ($f['datalist'] as $o): ?><option value="<?= e($o) ?>"><?php endforeach; ?></datalist>
        <?php endif; ?>
    <?php endswitch; ?>
    <?php if (!empty($f['help'])): ?><small class="form-hint"><?= e($f['help']) ?></small><?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</form>
