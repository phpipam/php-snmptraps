<?php

declare(strict_types=1);

namespace Snmptraps\Web;

use Snmptraps\Auth;
use Snmptraps\Config;
use Snmptraps\Database;
use Snmptraps\Schema;
use Snmptraps\Mib;
use Snmptraps\TrapRepository;
use Snmptraps\Web\Controller\Ajax;
use Snmptraps\Web\Controller\Pages;
use Snmptraps\Web\Controller\Settings;
use Throwable;

/**
 * Front controller: routing, authentication gate, CSRF gate, layout.
 *
 * URLs (relative to Config::$base):
 *   /                       dashboard
 *   /severity/{group}/      all|major|minor|informational|unknown
 *   /host/[{hostname}/]
 *   /live/
 *   /search/
 *   /trap/{id}/
 *   /message/{base64url}/
 *   /settings/[{page}/]     administrators
 *   /settings/users/{id}/   user details
 *   /login/  /logout/
 *   /ajax/{action}/         XHR endpoints (HTML fragments / JSON)
 */
final class App
{
    public readonly Database $db;
    public readonly Auth $auth;
    public readonly View $view;
    private ?TrapPresenter $presenter = null;
    private ?TrapRepository $traps = null;
    private ?Schema $schema = null;

    /** current route segments */
    public array $segments = [];
    public bool $isAjax = false;

    public function __construct(public readonly Config $config)
    {
        $this->db        = Database::fromConfig($config);
        $this->auth      = new Auth($config, $this->db);
        $this->view      = new View(APP_ROOT . '/views', $this);
    }

    /** Trap HTML renderer; created after login so it knows the user's role */
    public function presenter(): TrapPresenter
    {
        return $this->presenter ??= new TrapPresenter($this->config->stripHostnameDomain, $this->auth->isOperator(), $this->config->repeatWindowMinutes);
    }

    public function traps(): TrapRepository
    {
        // repeated traps are collapsed to the latest one once migration 1.0.3 is applied
        return $this->traps ??= new TrapRepository($this->db, $this->auth->permittedHosts(), $this->schema()->has('1.0.3'));
    }

    /** pending database migrations (cached for a minute) */
    public function schema(): Schema
    {
        return $this->schema ??= new Schema($this->db, $this->config->db['name']);
    }

    public function mib(): Mib
    {
        return new Mib($this->config->mibDirectory);
    }

    public function run(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');

        $this->segments = $this->parsePath();
        $route = $this->segments[0] ?? 'dashboard';
        $this->isAjax = $route === 'ajax';

        try {
            $this->auth->startSession();

            if ($this->isPost() && !$this->auth->checkCsrf($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
                throw new HttpException(400, 'Invalid or expired form token, please reload the page and try again.');
            }

            if ($route === 'login' || $route === 'logout') {
                (new Pages($this))->{$route}();
                return;
            }

            if (!$this->auth->isAuthenticated()) {
                if ($this->isAjax) {
                    throw new HttpException(401, "Please login first! <hr><a class='btn btn-sm btn-default' href='" . e($this->config->base) . "login/'>Login</a>");
                }
                if (!$this->isPost()) {
                    $_SESSION['return_to'] = $_SERVER['REQUEST_URI'] ?? '';
                }
                $this->redirect('login/');
            }

            if ($this->config->screenshotMode) {
                // $screenshot_mode (config.php): mask hostnames / addresses in everything sent, for all users
                $anonymizer = new Anonymizer($this->db->column(
                    'SELECT DISTINCT `hostname` FROM `traps` UNION SELECT `hostname` FROM `maintenance` UNION SELECT `hostname` FROM `exceptions`',
                ));
                ob_start($anonymizer->apply(...));
            }

            $this->dispatch($route);
        } catch (HttpException $e) {
            http_response_code($e->status);
            $this->renderError($e->getMessage());
        } catch (Throwable $e) {
            http_response_code(500);
            error_log('snmptraps: ' . $e);
            $this->renderError($this->config->debugging ? e(get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine()) : 'Internal error, see server log.');
        }
    }

    private function dispatch(string $route): void
    {
        $s = $this->segments;
        $pages = new Pages($this);
        $html = match ($route) {
            'dashboard' => $pages->dashboard(),
            'severity'  => $pages->severity($s[1] ?? 'all'),
            'host'      => $pages->host($s[1] ?? null),
            'live'      => $pages->live(),
            'search'    => $pages->search(),
            'trap'      => $pages->trap((int) ($s[1] ?? 0)),
            // legacy links used standard base64, which may contain "/"
            'message'   => $pages->message(implode('/', array_slice($s, 1))),
            'settings'  => (new Settings($this))->page($s[1] ?? 'severity_definitions', $s[2] ?? null),
            'ajax'      => (new Ajax($this))->handle($s[1] ?? ''),
            default     => throw new HttpException(404, 'Page not found'),
        };
        if ($html === null) {
            return;
        }
        if ($this->isAjax) {
            echo $html;
        } else {
            $this->layout($html);
        }
    }

    public function layout(string $content, ?string $title = null): void
    {
        echo $this->view->render('layout', ['content' => $content, 'title' => $title ?? $this->config->title]);
    }

    private function renderError(string $html): void
    {
        $box = View::alert('danger', $html);
        if ($this->isAjax || !$this->auth->isAuthenticated()) {
            // modal requests expect a fragment; unauthenticated users get a bare page
            echo $this->isAjax ? $box : $this->view->render('bare', ['content' => $box]);
            return;
        }
        $this->layout("<div class='container'>$box</div>");
    }

    /** @return list<string> */
    private function parsePath(): array
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = $this->config->base;
        if (str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        } elseif (rtrim($path, '/') === rtrim($base, '/')) {
            $path = '';
        }
        $path = preg_replace('#^index\.php/?#', '', $path) ?? '';
        $segments = array_values(array_filter(explode('/', $path), static fn ($p) => $p !== ''));
        return array_map('rawurldecode', $segments);
    }

    public function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public function redirect(string $to): never
    {
        $url = preg_match('#^(https?:)?/#', $to) ? $to : $this->config->base . $to;
        header('Location: ' . $url, true, 303);
        exit;
    }

    public function input(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public function requireAdmin(): void
    {
        if (!$this->auth->isAdmin()) {
            throw new HttpException(403, 'Administrator level privileges required');
        }
    }

    public function requireOperator(): void
    {
        if (!$this->auth->isOperator()) {
            throw new HttpException(403, 'Operator level privileges required');
        }
    }

    public function assetVersion(): string
    {
        if ($this->config->debugging) {
            return (string) time();
        }
        // changes whenever a vendored or app asset is replaced (assets are cached for 7 days)
        $mtime = 0;
        foreach (['css/app.css', 'js/app.js', 'css/tabler/tabler.min.css', 'js/tabler.min.js'] as $f) {
            $mtime = max($mtime, (int) @filemtime(APP_ROOT . '/public/' . $f));
        }
        return APP_VERSION . '.' . base_convert((string) $mtime, 10, 36);
    }
}
