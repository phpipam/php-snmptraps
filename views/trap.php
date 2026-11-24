<?php
/** @var Snmptraps\Web\App $app */
/** @var array $trap */
/** @var array|null $mib */
?>
<div class="container">
    <h4>Details for trap id <?= (int) $trap['id'] ?></h4><hr>
    <?= $app->view->render('partials/trap-details', ['trap' => $trap, 'mib' => $mib]) ?>
</div>
