<?php
/**
 * Trap details (used on the trap page and in the quick-view modal).
 *
 * @var Snmptraps\Web\App $app
 * @var array $trap
 * @var array|null $mib
 */
$p = $app->presenter();
$items = [
    'Host'     => $p->hostname((string) $trap['hostname'], true),
    'IP'       => e($trap['ip']),
    'Date'     => e($trap['date']),
    'Severity' => $p->severity((string) $trap['severity']),
    'Message'  => $p->message($trap),
    'OID'      => '<code>' . e($trap['oid']) . '</code>',
];
?>
<div class="datagrid mb-3">
    <?php foreach ($items as $label => $html): ?>
    <div class="datagrid-item">
        <div class="datagrid-title"><?= e($label) ?></div>
        <div class="datagrid-content"><?= $html ?></div>
    </div>
    <?php endforeach; ?>
</div>

<h4 class="mt-4">Content</h4>
<pre class="trap-pre"><?= e($trap['content']) ?></pre>

<?php if ($mib !== null && ($mib['description'] !== '' || $mib['objects'] !== [])): ?>
<h4 class="mt-4">MIB description <span class="text-secondary small fw-normal ms-2"><?= e($mib['file']) ?></span></h4>
<p class="text-secondary"><?= e_text($mib['description']) ?></p>
<?php if ($mib['objects'] !== []): ?>
<div><?php foreach ($mib['objects'] as $o): ?><span class="badge badge-outline text-secondary me-1 mb-1"><?= e($o) ?></span><?php endforeach; ?></div>
<?php endif; ?>
<?php endif; ?>

<h4 class="mt-4">Raw trap</h4>
<pre class="trap-pre text-secondary"><?= e($trap['raw']) ?></pre>
