<?php
/** @var string $title */
/** @var string $body   trusted, already escaped HTML */
/** @var string $button */
/** @var string $action relative URL the #modal-form is posted to */
?>
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <h4 class="modal-title"><?= e($title) ?></h4>
</div>
<div class="modal-body">
<?= $body ?>
</div>
<div class="modal-footer">
    <div class="btn-group">
        <button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Close</button>
        <?php if ($button !== '' && $action !== ''): ?>
        <button type="button" class="btn btn-sm btn-primary modal-execute" data-action="<?= e($action) ?>"><?= e($button) ?></button>
        <?php endif; ?>
    </div>
    <div class="modal-result text-left" style="margin-top:10px;display:none"></div>
</div>
