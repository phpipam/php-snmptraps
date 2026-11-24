<?php
/** @var Snmptraps\Web\App $app */
/** @var string $content */
/** @var string $title */
use Snmptraps\Severity;

$cfg   = $app->config;
$user  = $app->auth->user();
$route = $app->segments[0] ?? 'dashboard';
$sub   = $app->segments[1] ?? null;
$v     = e($app->assetVersion());

// auto reload (seconds) - not on pages where it would interrupt work
$reload = (int) ($user['reload_page'] ?? 0);
if (in_array($route, ['settings', 'live', 'search', 'trap', 'message'], true)) {
    $reload = 0;
}
$paused = ($_COOKIE['stop-refresh'] ?? '') === '1';

$nav = static function (string $label, string $icon, string $href, bool $active): string {
    return '<li class="nav-item' . ($active ? ' active' : '') . '"><a class="nav-link" href="' . e($href) . '">'
        . '<span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ' . e($icon) . '"></i></span>'
        . '<span class="nav-link-title">' . e($label) . '</span></a></li>';
};
?>
<!DOCTYPE html>
<html lang="en" <?= Snmptraps\Web\View::THEME ?>>
<head>
    <base href="<?= e($cfg->base) ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e($app->auth->csrfToken()) ?>">
    <title><?= e($title) ?></title>
    <link rel="icon" type="image/png" href="css/favicon.png">
    <link rel="stylesheet" href="css/tabler/tabler.min.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/tabler/tabler-themes.min.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/tabler/tabler-icons.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/app.css?v=<?= $v ?>">
</head>
<body>
<div class="loading"><div class="progress progress-sm"><div class="progress-bar progress-bar-indeterminate"></div></div></div>

<?php if ($reload > 0): ?>
<div class="progress progress-reload"<?= $paused ? ' style="display:none"' : '' ?>>
    <div class="progress-bar progress-bar-reload" style="width:100%" role="progressbar" data-seconds="<?= $reload ?>"></div>
</div>
<?php endif; ?>

<div class="page">
<header class="navbar navbar-expand-md sticky-top d-print-none">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu" aria-controls="navbar-menu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="navbar-brand navbar-brand-autodark pe-0 pe-md-3">
            <a href="./" class="text-reset text-decoration-none d-flex align-items-center">
                <i class="ti ti-broadcast text-primary me-2 brand-icon"></i>
                <span class="brand-title"><?= e($cfg->title) ?></span>
            </a>
        </div>

        <!-- right items -->
        <div class="navbar-nav flex-row order-md-last align-items-center">
            <div class="nav-item d-none d-sm-flex<?= $route === 'search' ? ' active' : '' ?>">
                <a href="search/" class="nav-link px-2" data-toggle="tooltip-nofollow" tippy-title="Search traps"><i class="ti ti-search icon"></i></a>
            </div>
            <?php if ($reload > 0): ?>
            <div class="nav-item d-none d-sm-flex">
                <a href="#" class="nav-link px-2 toggle-timer" data-toggle="tooltip-nofollow" tippy-title="Toggle auto reload">
                    <i class="ti <?= $paused ? 'ti-player-play' : 'ti-player-pause' ?> icon"></i>
                    <span class="badge bg-blue text-blue-fg badge-notification badge-timer"<?= $paused ? ' style="display:none"' : '' ?>><?= $reload ?>s</span>
                </a>
            </div>
            <?php endif; ?>
            <?php if ($app->auth->isAdmin()): ?>
            <div class="nav-item d-none d-sm-flex<?= $route === 'settings' ? ' active' : '' ?>">
                <a href="settings/" class="nav-link px-2" data-toggle="tooltip-nofollow" tippy-title="Settings"><i class="ti ti-settings icon"></i></a>
            </div>
            <?php endif; ?>
            <div class="nav-item dropdown">
                <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Open user menu">
                    <div class="d-none d-xl-block ps-2 text-end">
                        <div><?= e($user['real_name'] ?: $user['username']) ?></div>
                        <div class="mt-1 small text-secondary"><?= e(ucfirst((string) $user['role'])) ?></div>
                    </div>
                    <span class="avatar avatar-sm ms-2 bg-transparent"><i class="ti ti-user-circle" style="font-size:30px"></i><?php if (Snmptraps\Web\UserPresenter::inQuietTime($user)): ?><span class="badge bg-yellow badge-notification badge-blink" title="Quiet time active - no notifications"></span><?php endif; ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    <span class="dropdown-header"><?= e($user['username']) ?></span>
                    <div class="px-3 pb-2 small text-secondary">
                        <div class="mb-1">Quiet time</div>
                        <?= Snmptraps\Web\UserPresenter::quietBadge($user) ?>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="ajax/profile/" class="dropdown-item load-modal"><i class="ti ti-user-edit me-2"></i>Profile</a>
                    <?php if ($app->auth->isAdmin()): ?>
                    <a href="settings/" class="dropdown-item"><i class="ti ti-settings me-2"></i>Settings</a>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <a href="logout/" class="dropdown-item"><i class="ti ti-logout me-2"></i>Logout</a>
                </div>
            </div>
        </div>

        <!-- menu -->
        <div class="collapse navbar-collapse" id="navbar-menu">
            <div class="d-flex flex-column flex-md-row flex-fill align-items-stretch align-items-md-center">
                <ul class="navbar-nav">
                    <?= $nav('Dashboard', 'ti-layout-dashboard', 'dashboard/', $route === 'dashboard') ?>
                    <?= $nav('All traps', 'ti-list', 'severity/all/', $route === 'severity' && $sub === 'all') ?>
                    <li class="nav-item dropdown<?= $route === 'severity' && $sub !== 'all' ? ' active' : '' ?>">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-alert-triangle"></i></span>
                            <span class="nav-link-title">Severities</span>
                        </a>
                        <div class="dropdown-menu">
                            <?php foreach (Severity::GROUPS as $slug => $g): ?>
                            <a class="dropdown-item<?= $sub === $slug ? ' active' : '' ?>" href="severity/<?= e($slug) ?>/">
                                <span class="status-dot bg-<?= Severity::groupColor($slug) ?> d-block me-2"></span>
                                <?= e($g['label']) ?> <span class="text-secondary ms-2 small"><?= e(implode(', ', $g['levels'])) ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </li>
                    <?= $nav('Live', 'ti-bolt', 'live/', $route === 'live') ?>
                    <?= $nav('Hosts', 'ti-server', 'host/', $route === 'host') ?>
                    <?= $nav('Search', 'ti-search', 'search/', $route === 'search') ?>
                </ul>
            </div>
        </div>
    </div>
</header>

<div class="page-wrapper">
    <div class="page-body">
        <div class="container-fluid">
<?php $pending = $app->auth->isAdmin() ? $app->schema()->pending() : []; if ($pending !== []): ?>
            <div class="alert alert-warning d-flex align-items-start mb-3" role="alert">
                <i class="ti ti-database-exclamation me-2 fs-2"></i>
                <div>
                    <strong>Database migrations not applied:</strong>
                    <?php foreach ($pending as $ver): ?>
                    <div><code>db/migrations/<?= e($ver) ?>.sql</code> &ndash; <?= e(Snmptraps\Schema::MIGRATIONS[$ver]) ?></div>
                    <?php endforeach; ?>
                    <div class="small mt-1">Run <code>php bin/migrate.php</code> on the server (applies them in order), see README. Some features are disabled until then.</div>
                </div>
            </div>
<?php endif; ?>
<?= $content ?>
        </div>
    </div>
    <footer class="footer footer-transparent d-print-none py-3">
        <div class="container-fluid text-secondary small d-flex">
            <span><?= e($cfg->footer) ?></span>
            <span class="ms-auto">php-snmptraps <?= e(APP_VERSION) ?></span>
        </div>
    </footer>
</div>
</div>

<div class="modal fade" id="modal1" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content"></div>
    </div>
</div>

<script src="js/jquery-3.7.1.min.js"></script>
<script src="js/popper.min.js"></script>
<script src="js/tippy-bundle.umd.min.js"></script>
<!-- Tabler Datepicker / InputMask dependencies (must load before tabler.min.js) -->
<script src="js/vendor/vanilla-calendar-pro.js?v=<?= $v ?>"></script>
<script src="js/vendor/imask.min.js?v=<?= $v ?>"></script>
<script src="js/tabler.min.js?v=<?= $v ?>"></script>
<script src="js/app.js?v=<?= $v ?>"></script>
</body>
</html>
