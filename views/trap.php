<?php
/** @var Snmptraps\Web\App $app */
/** @var array $trap */
/** @var array|null $mib */
$id = (int) $trap['id'];
?>
<div class="page-header mb-3">
    <div class="row align-items-center">
        <div class="col">
            <div class="page-pretitle">Trap <?= $id ?></div>
            <h2 class="page-title"><?= e($trap['message']) ?></h2>
        </div>
        <div class="col-auto ms-auto d-print-none btn-list">
            <a href="message/<?= e(b64url_encode((string) $trap['message'])) ?>/" class="btn"><i class="ti ti-list-search me-1"></i>All with this message</a>
            <a href="host/<?= e(rawurlencode((string) $trap['hostname'])) ?>/" class="btn"><i class="ti ti-server me-1"></i>Host traps</a>
            <?php if ($app->auth->isOperator()): ?>
            <a href="ajax/message-edit/?action=define&amp;id=<?= $id ?>" class="btn load-modal"><i class="ti ti-adjustments me-1"></i>Define severity</a>
            <a href="ajax/message-edit/?action=ignore&amp;id=<?= $id ?>" class="btn load-modal"><i class="ti ti-volume-off me-1"></i>Ignore</a>
            <a href="ajax/message-edit/?action=delete&amp;id=<?= $id ?>" class="btn btn-outline-danger load-modal"><i class="ti ti-trash me-1"></i>Delete</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <?= $app->view->render('partials/trap-details', ['trap' => $trap, 'mib' => $mib]) ?>
    </div>
</div>
