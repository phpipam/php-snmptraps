<?php
/** @var Snmptraps\Web\App $app */
/** @var string|null $error */
/** @var string|null $notice */
/** @var string $username */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= e($app->config->base) ?>">
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=0.7, maximum-scale=1, user-scalable=yes">
    <title><?= e($app->config->title) ?> - Login</title>
    <link rel="icon" type="image/png" href="css/favicon_big.png">
    <link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="css/bootstrap/bootstrap-custom-black.css">
    <link rel="stylesheet" href="css/font-awesome/font-awesome.min.css">
</head>
<body>
<hr>
<div class="wrapper">
<div class="content_overlay">
<div class="container" style="margin-top:20px;max-width:600px;">
    <h4 class="red text-center">Login to <?= e($app->config->title) ?></h4>
    <hr>
    <div class="text-center">Please enter your username / password to login. In case of any issues please contact the system administrator.</div>

    <div class="login">
    <form name="login" id="login" method="post" action="login/">
        <input type="hidden" name="csrf_token" value="<?= e($app->auth->csrfToken()) ?>">
        <div class="row">
            <div class="col-xs-12"><strong>Username</strong></div>
            <div class="col-xs-12">
                <input type="text" id="username" name="trapusername" value="<?= e($username) ?>" class="login form-control input-sm" placeholder="Username" autofocus autocomplete="username" autocapitalize="off" spellcheck="false" required>
            </div>
            <div class="col-xs-12" style="margin-top:10px;"><strong>Password</strong></div>
            <div class="col-xs-12">
                <input type="password" id="password" name="trappassword" class="login form-control input-sm" placeholder="Password" autocomplete="current-password" required>
            </div>
            <div class="col-xs-12">
                <hr>
                <input type="submit" value="Login" class="btn btn-sm btn-default pull-right">
            </div>
        </div>
    </form>
    </div>

    <div id="loginCheck" style="margin-top:20px;">
        <?php if ($error !== null): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <?php if ($notice !== null): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
    </div>
</div>
</div>
</div>
</body>
</html>
