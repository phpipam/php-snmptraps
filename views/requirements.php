<?php
/**
 * Error page for missing PHP extensions (rendered by public/index.php without the App,
 * which may not work without them).
 *
 * @var Snmptraps\Config $config
 * @var array<string, string> $missing extension => what needs it
 */
?>
<!DOCTYPE html>
<html lang="en" <?= Snmptraps\Web\View::theme() ?>>
<head>
    <base href="<?= e($config->base) ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($config->title) ?></title>
    <link rel="icon" type="image/png" href="css/favicon.png">
    <link rel="stylesheet" href="css/tabler/tabler.min.css">
    <link rel="stylesheet" href="css/tabler/tabler-themes.min.css">
</head>
<body class="d-flex flex-column">
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-title">Missing PHP extensions</h4>
            <div class="text-secondary mb-2">php-snmptraps <?= e(APP_VERSION) ?> needs the following PHP extensions, which are not loaded by PHP <?= e(PHP_VERSION) ?> (<?= e(PHP_SAPI) ?>):</div>
            <ul class="mb-2">
                <?php foreach ($missing as $ext => $why): ?>
                    <li><strong><?= e($ext) ?></strong> <span class="text-secondary">- <?= e($why) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <div class="text-secondary">Install / enable them and restart the PHP service (php-fpm or the web server).</div>
        </div>
    </div>
</div>
</body>
</html>
