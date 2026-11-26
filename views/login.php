<?php
/**
 * Sign-in pages (no layout / navbar): password, TOTP code, TOTP setup.
 *
 * @var Snmptraps\Web\App $app
 * @var string $content  card(s) from partials/login-*.php
 */
$v = e($app->assetVersion());
?>
<!DOCTYPE html>
<html lang="en" <?= Snmptraps\Web\View::theme() ?>>
<head>
    <base href="<?= e($app->config->base) ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e($app->auth->csrfToken()) ?>">
    <title><?= e($app->config->title) ?> - Login</title>
    <link rel="icon" type="image/png" href="css/favicon.png">
    <link rel="stylesheet" href="css/tabler/tabler.min.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/tabler/tabler-themes.min.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/tabler/tabler-icons.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/app.css?v=<?= $v ?>">
</head>
<body class="d-flex flex-column">
<div class="page page-center">
    <div class="container container-tight py-4">
        <h2 class="page-title d-flex justify-content-center align-items-center mb-4 mt-5">
            <i class="ti ti-broadcast text-primary me-2 brand-icon"></i><span class="brand-title"><?= e($app->config->title) ?></span>
        </h2>
        <?= $content ?>
    </div>
</div>
<script src="js/passkey.js?v=<?= $v ?>"></script>
</body>
</html>
