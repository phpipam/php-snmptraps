<?php
/** @var Snmptraps\Web\App $app */
/** @var string $message */
/** @var list<array> $traps */
/** @var array|null $mib */
use Snmptraps\Web\TrapPresenter;

$p = $app->presenter();
$first = $traps[0] ?? null;
$hosts = array_count_values(array_map(static fn ($t) => (string) $t['hostname'], $traps));
arsort($hosts);
?>
<div class="page-header mb-3">
    <div class="row align-items-center">
        <div class="col">
            <div class="page-pretitle">Message</div>
            <h2 class="page-title"><?= e($message) ?></h2>
        </div>
        <?php if ($first !== null && $app->auth->isOperator()): ?>
        <div class="col-auto ms-auto d-print-none btn-list">
            <a href="ajax/message-edit/?action=define&amp;id=<?= (int) $first['id'] ?>" class="btn load-modal"><i class="ti ti-adjustments me-1"></i>Define severity</a>
            <a href="ajax/message-edit/?action=ignore&amp;id=<?= (int) $first['id'] ?>" class="btn load-modal"><i class="ti ti-volume-off me-1"></i>Ignore</a>
            <a href="ajax/message-edit/?action=delete&amp;id=<?= (int) $first['id'] ?>" class="btn btn-outline-danger load-modal"><i class="ti ti-trash me-1"></i>Delete</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="row row-cards">
<?php if ($first !== null): ?>
<div class="col-12">
<div class="card">
    <div class="card-body">
        <?php
        $hostsHtml = '';
        foreach (array_slice($hosts, 0, 10, true) as $h => $n) {
            $hostsHtml .= '<span class="badge badge-outline text-secondary me-1 mb-1">' . e($h) . ' &middot; ' . $n . '</span>';
        }
        if (count($hosts) > 10) {
            $hostsHtml .= '<span class="text-secondary small">+' . (count($hosts) - 10) . ' more</span>';
        }
        $items = [
            'OID'         => '<code>' . e($first['oid']) . '</code>',
            'Severity'    => '<span class="d-inline-block" style="width:110px">' . $p->severity((string) $first['severity']) . '</span>',
            'Latest'      => e($first['date']),
            'Traps shown' => count($traps) . (count($traps) >= 200 ? ' (latest 200)' : ''),
            'Hosts'       => $hostsHtml,
            'MIB file'    => $mib ? e($mib['file']) : '<span class="text-secondary">not found</span>',
        ]; ?>
        <?php foreach ($items as $label => $html): ?>
        <div class="row py-1">
            <div class="col-12 col-sm-3 col-lg-2 text-secondary"><?= e($label) ?></div>
            <div class="col"><?= $html ?></div>
        </div>
        <?php endforeach; ?>
        <?php if ($mib && $mib['description'] !== ''): ?>
        <div class="mt-3 text-secondary"><?= e_text($mib['description']) ?></div>
        <?php endif; ?>
        <?php if ($mib && $mib['objects'] !== []): ?>
        <div class="mt-2"><?php foreach ($mib['objects'] as $o): ?><span class="badge badge-outline text-secondary me-1 mb-1"><?= e($o) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
    </div>
</div>
</div>
<?php endif; ?>

<div class="col-12">
<div class="card">
    <div class="card-header"><h3 class="card-title">Traps</h3></div>
    <?= $app->view->render('partials/trap-table', ['traps' => $traps, 'columns' => TrapPresenter::LIST, 'empty' => 'No traps with this message.']) ?>
</div>
</div>
</div>
