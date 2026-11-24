<?php
/** @var Snmptraps\Web\App $app */
/** @var string $content */
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <base href="<?= e($app->config->base) ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($app->config->title) ?></title>
    <link rel="icon" type="image/png" href="css/favicon.png">
    <link rel="stylesheet" href="css/tabler/tabler.min.css">
    <link rel="stylesheet" href="css/app.css?v=<?= e($app->assetVersion()) ?>">
</head>
<body class="d-flex flex-column">
<div class="page page-center">
    <div class="container container-tight py-4">
        <?= $content ?>
        <a class="btn w-100" href="<?= e($app->config->base) ?>">Back</a>
    </div>
</div>
</body>
</html>
