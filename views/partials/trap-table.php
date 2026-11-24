<?php
/**
 * Trap table inside a card.
 *
 * @var Snmptraps\Web\App $app
 * @var list<array> $traps
 * @var list<string> $columns  TrapPresenter::LIST / COMPACT
 * @var string $empty          text when there are no traps
 * @var string $tableClass     optional extra table class
 */
$p = $app->presenter();
?>
<?php if ($traps === []): ?>
<div class="card-body"><span class="text-secondary"><?= e($empty ?? 'No traps found.') ?></span></div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-vcenter card-table table-hover table-traps <?= e($tableClass ?? '') ?>">
        <?= $p->headers($columns) ?>
        <tbody><?= $p->rows($traps, $columns) ?></tbody>
    </table>
</div>
<?php endif; ?>
