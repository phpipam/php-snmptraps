<?php
/** @var Snmptraps\Web\App $app */
/** @var string $content */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= e($app->config->base) ?>">
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($app->config->title) ?></title>
    <link rel="icon" type="image/png" href="css/favicon_big.png">
    <link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="css/bootstrap/bootstrap-custom-black.css">
    <link rel="stylesheet" href="css/font-awesome/font-awesome.min.css">
</head>
<body>
<div class="container" style="margin-top:40px;max-width:600px;">
    <?= $content ?>
    <a class="btn btn-sm btn-default" href="<?= e($app->config->base) ?>">Back</a>
</div>
</body>
</html>
