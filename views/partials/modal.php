<?php
/** @var string $title */
/** @var string $body   trusted, already escaped HTML */
/** @var string $button */
/** @var string $action relative URL the #modal-form is posted to */
/** @var string $color  Tabler colour of the status bar */
?>
<div class="modal-status bg-<?= e($color ?? 'azure') ?>"></div>
<div class="modal-header">
    <h5 class="modal-title"><?= e($title) ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
<?= $body ?>
</div>
<div class="modal-footer flex-column align-items-stretch">
    <div class="modal-result" style="display:none"></div>
    <div class="d-flex">
        <button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button>
        <?php if ($button !== '' && $action !== ''): ?>
        <button type="button" class="btn btn-<?= ($color ?? '') === 'red' ? 'danger' : 'primary' ?> modal-execute" data-action="<?= e($action) ?>"><?= e($button) ?></button>
        <?php endif; ?>
    </div>
</div>
