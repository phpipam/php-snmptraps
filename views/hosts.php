<?php
/** @var Snmptraps\Web\App $app */
/** @var list<string> $hosts */
?>
<div class="row row-cards">
<div class="col-12">
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-server me-2"></i>Hosts <span class="badge bg-transparent text-secondary ms-2"><?= count($hosts) ?></span></h3>
        <div class="card-actions">
            <div class="input-icon">
                <span class="input-icon-addon"><i class="ti ti-filter"></i></span>
                <input type="text" class="form-control form-control-sm host-filter" placeholder="Filter hosts" autofocus>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if ($hosts === []): ?>
        <span class="text-secondary">No hosts found.</span>
        <?php else: ?>
        <div class="row g-2 host-list">
            <?php foreach ($hosts as $h): ?>
            <div class="col-12 col-sm-6 col-lg-4 col-xxl-3 host-item" data-host="<?= e(strtolower($h)) ?>">
                <a href="host/<?= e(rawurlencode($h)) ?>/" class="btn btn-ghost-secondary w-100 justify-content-start text-truncate"><i class="ti ti-server-2 me-2 text-secondary"></i><?= e($h) ?></a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
