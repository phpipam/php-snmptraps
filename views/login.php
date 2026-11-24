<?php
/** @var Snmptraps\Web\App $app */
/** @var string|null $error */
/** @var string|null $notice */
/** @var string $username */
?>
<!DOCTYPE html>
<html lang="en" <?= Snmptraps\Web\View::theme() ?>>
<head>
    <base href="<?= e($app->config->base) ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($app->config->title) ?> - Login</title>
    <link rel="icon" type="image/png" href="css/favicon.png">
    <link rel="stylesheet" href="css/tabler/tabler.min.css">
    <link rel="stylesheet" href="css/tabler/tabler-themes.min.css">
    <link rel="stylesheet" href="css/tabler/tabler-icons.css">
    <link rel="stylesheet" href="css/app.css?v=<?= e($app->assetVersion()) ?>">
</head>
<body class="d-flex flex-column">
<div class="page page-center">
    <div class="container container-tight py-4">
        <h2 class="page-title d-flex justify-content-center align-items-center mb-4 mt-5">
            <i class="ti ti-broadcast text-primary me-2 brand-icon"></i><span class="brand-title"><?= e($app->config->title) ?></span>
        </h2>
        <div class="card card-md">
            <div class="card-body">
                <form method="post" action="login/" autocomplete="off" name="login">
                    <input type="hidden" name="csrf_token" value="<?= e($app->auth->csrfToken()) ?>">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="trapusername" value="<?= e($username) ?>" placeholder="Username" autocomplete="username" autocapitalize="off" spellcheck="false" autofocus required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="trappassword" placeholder="Your password" autocomplete="current-password" required>
                    </div>
                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary w-100">Sign in</button>
                    </div>
                    <?php if ($error !== null): ?><div class="alert alert-danger mt-3 mb-0"><?= e($error) ?></div><?php endif; ?>
                    <?php if ($notice !== null): ?><div class="alert alert-success mt-3 mb-0"><?= e($notice) ?></div><?php endif; ?>
                </form>
            </div>
            <div class="hr-text">info</div>
            <div class="card-body text-secondary d-flex justify-content-center text-center">
                Use your domain account or the local account provided by the administrator.
            </div>
        </div>
    </div>
</div>
</body>
</html>
