<?php

declare(strict_types=1);

namespace Snmptraps\Web\Controller;

use DateTimeImmutable;
use Snmptraps\Severity;
use Snmptraps\Web\App;
use Snmptraps\Web\DateTimeField;
use Snmptraps\Web\HttpException;
use Snmptraps\Web\TrapPresenter;
use Snmptraps\Web\View;

/**
 * Full HTML pages.
 */
final class Pages
{
    public function __construct(private readonly App $app)
    {
    }

    public function login(): void
    {
        $auth = $this->app->auth;
        $error = null;

        if ($this->app->isPost()) {
            $error = $auth->login($this->app->input('trapusername'), (string) ($_POST['trappassword'] ?? ''));
            if ($error === null) {
                $to = (string) ($_SESSION['return_to'] ?? '');
                unset($_SESSION['return_to']);
                // only allow local paths below the application base
                $this->app->redirect(str_starts_with($to, $this->app->config->base) && !str_contains($to, '//') ? $to : '');
            }
        } elseif ($auth->isAuthenticated()) {
            $this->app->redirect('');
        }

        echo $this->app->view->render('login', [
            'error'    => $error,
            'username' => $this->app->input('trapusername'),
            'notice'   => isset($_GET['loggedout']) ? 'You have logged out' : null,
        ]);
    }

    public function logout(): void
    {
        $this->app->auth->logout();
        $this->app->redirect('login/?loggedout=1');
    }

    public function dashboard(): string
    {
        $widgets = [];
        foreach ($this->app->auth->dashLayout() as $group => $layout) {
            $widgets[$group] = [
                'title'  => Severity::GROUPS[$group]['title'],
                'width'  => $layout['width'],
                'traps'  => $this->app->traps()->find(['severities' => Severity::GROUPS[$group]['levels']], $layout['rows']),
            ];
        }
        return $this->app->view->render('dashboard', ['widgets' => $widgets]);
    }

    public function severity(string $group): string
    {
        if ($group !== 'all' && !isset(Severity::GROUPS[$group])) {
            throw new HttpException(404, 'Invalid severity group');
        }
        $filter = $group === 'all' ? [] : ['severities' => Severity::GROUPS[$group]['levels']];
        $title  = $group === 'all' ? 'All traps' : Severity::GROUPS[$group]['title'];
        return $this->app->view->render('list', $this->listing($filter) + [
            'title' => $title,
            'color' => Severity::groupColor($group),
            'base'  => "severity/$group/",
        ]);
    }

    public function host(?string $hostname): string
    {
        if ($hostname === null) {
            return $this->app->view->render('hosts', ['hosts' => $this->app->traps()->hosts()]);
        }
        if (!$this->app->auth->canSeeHost($hostname)) {
            throw new HttpException(403, 'Not allowed to view this host');
        }
        return $this->app->view->render('list', $this->listing(['hostname' => $hostname]) + [
            'title' => 'Traps for ' . $hostname,
            'color' => 'secondary',
            'base'  => 'host/' . rawurlencode($hostname) . '/',
        ]);
    }

    /**
     * Paginated trap list: ?page=N&limit=50|100|250|500&q=quick-filter
     */
    private function listing(array $filter): array
    {
        $limit = (int) $this->app->input('limit', '50');
        $limit = in_array($limit, self::LIMITS, true) ? $limit : 50;
        $q = mb_substr($this->app->input('q'), 0, 200);
        if ($q !== '') {
            $filter['search'] = $q;
        }
        $repo  = $this->app->traps();
        $total = $repo->count($filter);
        $pages = max(1, (int) ceil($total / $limit));
        $page  = max(1, min($pages, (int) $this->app->input('page', '1')));

        return [
            'traps' => $repo->find($filter, $limit, ($page - 1) * $limit),
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
            'limit' => $limit,
            'q'     => $q,
        ];
    }

    public const LIMITS = [50, 100, 250, 500];

    public function live(): string
    {
        return $this->app->view->render('live', ['traps' => $this->app->traps()->find([], 100)]);
    }

    public function search(): string
    {
        $hosts = $this->app->traps()->hosts();
        $form = [
            'hostname' => $this->app->input('hostname', 'all'),
            'severity' => $this->app->input('severity', 'all'),
            'from'     => DateTimeField::combine($_GET, 'from', '00:00'),
            'to'       => DateTimeField::combine($_GET, 'to', '23:59:59'),
            'text'     => $this->app->input('text'),
            'order'    => $this->app->input('order', 'desc') === 'asc' ? 'asc' : 'desc',
            'limit'    => in_array((int) $this->app->input('limit', '100'), [50, 100, 250, 500], true) ? (int) $this->app->input('limit', '100') : 100,
        ];

        $errors = [];
        $results = null;
        if (isset($_GET['search'])) {
            $filter = [];
            if ($form['hostname'] !== 'all') {
                if (in_array($form['hostname'], $hosts, true)) {
                    $filter['hostname'] = $form['hostname'];
                } else {
                    $errors[] = 'Invalid hostname';
                }
            }
            if ($form['severity'] !== 'all') {
                if (Severity::isValid($form['severity'], true)) {
                    $filter['severities'] = [$form['severity']];
                } else {
                    $errors[] = 'Invalid severity';
                }
            }
            foreach (['from', 'to'] as $k) {
                if ($form[$k] === '') {
                    continue;
                }
                $d = self::parseDateTime($form[$k]);
                if ($d === null) {
                    $errors[] = "Invalid $k date";
                } else {
                    $filter[$k] = $d->format('Y-m-d H:i:s');
                }
            }
            if (isset($filter['from'], $filter['to']) && $filter['from'] > $filter['to']) {
                $errors[] = 'Invalid date span';
            }
            if ($form['text'] !== '') {
                $filter['text'] = $form['text'];
            }
            if ($errors === []) {
                $results = $this->app->traps()->find($filter, $form['limit'], 0, $form['order']);
            }
        }

        return $this->app->view->render('search', ['hosts' => $hosts, 'form' => $form, 'errors' => $errors, 'results' => $results]);
    }

    public function trap(int $id): string
    {
        $trap = $this->app->traps()->get($id);
        if ($trap === null) {
            throw new HttpException(404, 'Invalid trap id');
        }
        return $this->app->view->render('trap', ['trap' => $trap, 'mib' => $this->app->mib()->lookup((string) $trap['oid'])]);
    }

    public function message(string $encoded): string
    {
        $message = b64url_decode($encoded);
        if ($message === false) {
            throw new HttpException(404, 'Invalid message identifier');
        }
        $traps = $this->app->traps()->byMessage($message, 200);
        $mib = $traps === [] ? null : $this->app->mib()->lookup((string) $traps[0]['oid']);

        return $this->app->view->render('message', ['message' => $message, 'traps' => $traps, 'mib' => $mib]);
    }

    /** Accepts "Y-m-d H:i[:s]", "Y-m-d\TH:i[:s]" (datetime-local) and "Y-m-d" */
    public static function parseDateTime(string $s): ?DateTimeImmutable
    {
        $s = str_replace('T', ' ', trim($s));
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $fmt) {
            $d = DateTimeImmutable::createFromFormat('!' . $fmt, $s);
            if ($d !== false && $d->format($fmt) === $s) {
                return $d;
            }
        }
        return null;
    }
}
