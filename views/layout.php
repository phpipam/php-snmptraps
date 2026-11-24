<?php
/** @var Snmptraps\Web\App $app */
/** @var string $content */
/** @var string $title */
$cfg   = $app->config;
$user  = $app->auth->user();
$route = $app->segments[0] ?? 'dashboard';
$sub   = $app->segments[1] ?? null;
$v     = e($app->assetVersion());

$menu = [
    ['dashboard', null, 'fa-dashboard', 'Dashboard'],
    ['severity', 'all', 'fa-globe', 'All'],
    ['live', null, 'fa-bolt', 'Live'],
    ['severity', 'major', 'fa-exclamation-triangle', 'Major'],
    ['severity', 'minor', 'fa-exclamation', 'Medium'],
    ['severity', 'informational', 'fa-info', 'Minor'],
    ['severity', 'unknown', 'fa-question-circle', 'Unknown'],
    ['host', null, 'fa-sitemap', 'Hosts'],
    ['search', null, 'fa-search', 'Search'],
];
$reload = (int) ($user['reload_page'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= e($cfg->base) ?>">
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=0.6, maximum-scale=0.8, user-scalable=yes">
    <meta name="csrf-token" content="<?= e($app->auth->csrfToken()) ?>">
    <title><?= e($title) ?></title>
    <link rel="icon" type="image/png" href="css/favicon_big.png">
    <link rel="stylesheet" href="css/bootstrap/bootstrap.min.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/bootstrap/bootstrap-custom-black.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/bootstrap-table/bootstrap-table.min.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/font-awesome/font-awesome.min.css?v=<?= $v ?>">
    <script src="js/jquery-2.1.3.min.js"></script>
    <script src="js/bootstrap.min.js?v=<?= $v ?>"></script>
    <script src="js/tooltip.js?v=<?= $v ?>"></script>
    <script src="js/bootstrap-table/bootstrap-table.min.js?v=<?= $v ?>"></script>
    <script src="js/bootstrap-table/bootstrap-table-cookie.js?v=<?= $v ?>"></script>
    <script src="js/stickytableheaders/jquery.stickytableheaders.min.js?v=<?= $v ?>"></script>
    <script src="js/magic.js?v=<?= $v ?>"></script>
</head>
<body>
<div class="wrapper">
<div class="loading">Loading...<br><i class="fa fa-spinner fa-spin"></i></div>

<div class="container-fluid" style="margin:0;padding:0;">
<nav class="navbar navbar-default navbar-inverse" style="width:100%;">
    <div class="navbar-header">
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".navbar-collapse" aria-expanded="false">
            <span class="sr-only">Toggle navigation</span><span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
        </button>
    </div>
    <div id="navbar" class="collapse navbar-collapse" style="padding-left:0;padding-right:0;">
        <ul class="nav navbar-nav">
            <?php foreach ($menu as [$r, $p, $icon, $label]):
                $active = $route === $r && ($p === null || $p === $sub) ? 'active' : ''; ?>
            <li class="<?= $active ?>"><a href="<?= e($r . '/' . ($p !== null ? $p . '/' : '')) ?>"><i class="fa <?= e($icon) ?>"></i> <?= e($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <ul class="nav navbar-nav navbar-right">
            <?php if ($app->auth->isAdmin()): ?>
            <li class="<?= $route === 'settings' ? 'active' : '' ?>"><a href="settings/" class="settings"><i class="fa fa-cogs fa-red"></i> Settings</a></li>
            <?php endif; ?>
            <li class="dropdown" id="dropdown" style="margin-right:0;">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fa fa-user"></i> <span class="caret"></span></a>
                <ul class="dropdown-menu">
                    <li class="dropdown-header"><?= e($user['real_name']) ?><hr></li>
                    <li class="role"><a><?= e(ucwords((string) $user['role'])) ?></a></li>
                    <li><a href="ajax/profile/" class="load-modal"><i class="fa fa-pencil"></i> Profile</a></li>
                    <li><a href="logout/" class="settings"><i class="fa fa-sign-out"></i> Logout</a></li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
</div>

<div class="content_overlay">
<div class="container-fluid" id="mainContainer">
<?= $content ?>
</div>
</div>

<div class="modal fade" id="modal1" tabindex="-1" role="dialog"><div class="modal-dialog" role="document"><div class="modal-content"></div></div></div>
<div class="modal fade" id="modal2" tabindex="-2" role="dialog" style="z-index:1042"><div class="modal-dialog modal-lg" role="document"><div class="modal-content"></div></div></div>

<div class="pusher"></div>
</div>

<div class="footer">
<div class="row">
    <div class="col-xs-12 col-sm-6 col-lg-4"></div>
    <div class="col-xs-12 col-sm-6 col-lg-4">
        <?= e($cfg->footer) ?> :: version <?= e(APP_VERSION) ?> :: <a href="ajax/profile/" class="load-modal"><?= e($user['real_name']) ?></a>
    </div>
    <div class="col-xs-12 col-sm-12 col-lg-4">
        <?php if ($reload > 0 && $route !== 'settings' && $route !== 'live'): ?>
        <div class="pbar">
            <span class="pbar_text">Page will reload in <span class="pbar_percentage"><?= $reload ?></span> s <i class="fa fa-pause tooltip2 fa-toggle-timer" title="Click to toggle timer"></i></span>
            <div class="progress-bar progress-bar-gray" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width:0%;">0%</div>
            <div class="hidden progress-bar-reload"><?= $reload ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
</body>
</html>
