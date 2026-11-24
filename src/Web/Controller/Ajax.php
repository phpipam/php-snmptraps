<?php

declare(strict_types=1);

namespace Snmptraps\Web\Controller;

use PDOException;
use Snmptraps\Mib;
use Snmptraps\Severity;
use Snmptraps\Web\App;
use Snmptraps\Web\HttpException;
use Snmptraps\Web\TrapPresenter;
use Snmptraps\Web\View;

/**
 * XHR endpoints: /ajax/{action}/
 */
final class Ajax
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(string $action): ?string
    {
        $post = $this->app->isPost();
        return match ($action) {
            'live'         => $this->live(),
            'trap'         => $this->trap(),
            'message-edit' => $post ? $this->messageEditSubmit() : $this->messageEditForm(),
            'item'         => $post ? (new Settings($this->app))->itemSubmit() : (new Settings($this->app))->itemForm(),
            'profile'      => $post ? (new Settings($this->app))->profileSubmit() : (new Settings($this->app))->profileForm(),
            'mib'          => $post ? $this->mibSubmit() : $this->mibForm(),
            default        => throw new HttpException(404, 'Unknown action'),
        };
    }

    /** New rows for the live view since ?id= */
    private function live(): string
    {
        $id = (int) $this->app->input('id', '0');
        if ($id <= 0) {
            return 'False';
        }
        $rows = $this->app->traps()->find(['after_id' => $id], 500, 0, 'asc');
        if ($rows === []) {
            return 'False';
        }
        return $this->app->presenter()->rows(array_reverse($rows), TrapPresenter::LIST, true);
    }

    private function trap(): string
    {
        $trap = $this->app->traps()->get((int) $this->app->input('id'));
        if ($trap === null) {
            throw new HttpException(404, 'Invalid trap id');
        }
        $body = $this->app->view->render('partials/trap-details', [
            'trap' => $trap,
            'mib'  => $this->app->mib()->lookup((string) $trap['oid']),
        ]);
        return $this->modal('Trap ' . (int) $trap['id'], $body, '', '', Severity::tablerColor((string) $trap['severity']));
    }

    private function messageEditForm(): string
    {
        $this->app->requireOperator();
        $action = $this->app->input('action');
        if (!in_array($action, ['define', 'ignore', 'delete'], true)) {
            throw new HttpException(400, 'Invalid action');
        }
        $trap = $this->app->traps()->get((int) $this->app->input('id'));
        if ($trap === null) {
            throw new HttpException(404, 'Invalid trap id');
        }
        $titles = ['define' => 'Define severity for message', 'ignore' => 'Ignore message', 'delete' => 'Delete all traps for OID'];
        $body = $this->app->view->render('partials/message-edit', [
            'action' => $action,
            'trap'   => $trap,
            'hosts'  => $this->app->traps()->hosts(),
        ]);
        return $this->modal($titles[$action], $body, ucfirst($action), 'ajax/message-edit/', $action === 'delete' ? 'red' : 'azure');
    }

    private function messageEditSubmit(): string
    {
        $this->app->requireOperator();
        $action  = $this->app->input('action');
        $oid     = $this->app->input('oid');
        $content = mb_substr($this->app->input('content'), 0, 128);
        $comment = mb_substr($this->app->input('comment'), 0, 1000);
        if ($oid === '' || mb_strlen($oid) > 255) {
            return View::alert('danger', 'Missing OID');
        }
        $repo = $this->app->traps();

        try {
            switch ($action) {
                case 'define':
                    $severity = $this->app->input('newseverity');
                    if (!Severity::isValid($severity)) {
                        return View::alert('danger', 'Invalid severity');
                    }
                    $n = $repo->defineSeverity($oid, $severity, $content, $comment);
                    return View::alert('success', "Definition saved, $n existing traps updated");
                case 'delete':
                    $n = $repo->deleteByOid($oid, $content);
                    return View::alert('success', "$n traps removed");
                case 'ignore':
                    $host = $this->app->input('hostname', 'all');
                    if ($host !== 'all' && !in_array($host, $repo->hosts(), true)) {
                        return View::alert('danger', 'Invalid hostname');
                    }
                    $n = $repo->addException($oid, $host, $content, $comment);
                    return View::alert('success', "Exception created, $n existing traps removed");
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return View::alert('danger', 'Such a rule already exists');
            }
            throw $e;
        }
        return View::alert('danger', 'Invalid action');
    }

    private function mibForm(): string
    {
        $this->app->requireAdmin();
        $mib  = $this->app->mib();
        $file = $this->app->input('file');
        $text = $mib->read($file);
        if ($text === null) {
            throw new HttpException(404, 'Invalid MIB file');
        }
        $module = Mib::moduleName($text) ?? '';
        $items = [];
        foreach (Mib::notifications($text) as $name) {
            $oid = "$module::$name";
            $existing = $this->app->db->one("SELECT `severity`, `comment` FROM `severity_definitions` WHERE `oid` = ? AND IFNULL(`content`, '') = '' LIMIT 1", [$oid]);
            $n = Mib::notification($text, $name);
            $items[] = [
                'oid'      => $oid,
                'name'     => $name,
                'current'  => $existing['severity'] ?? null,
                'comment'  => $existing['comment'] ?? mb_substr(str_replace("\n", ' ', $n['description'] ?? ''), 0, 250),
            ];
        }
        $body = $this->app->view->render('partials/mib-definitions', ['file' => basename($file), 'module' => $module, 'items' => $items]);
        return $this->modal('Create severity definitions from ' . basename($file), $body, $items === [] ? '' : 'Save', 'ajax/mib/');
    }

    private function mibSubmit(): string
    {
        $this->app->requireAdmin();
        $defs = $_POST['def'] ?? [];
        if (!is_array($defs)) {
            return View::alert('danger', 'Invalid data');
        }
        $saved = 0;
        foreach ($defs as $d) {
            if (!is_array($d) || !isset($d['oid'], $d['severity']) || !is_string($d['oid']) || !is_string($d['severity'])) {
                continue;
            }
            if ($d['severity'] === '' || !Severity::isValid($d['severity'])) {
                continue; // "skip"
            }
            $this->app->traps()->defineSeverity(mb_substr($d['oid'], 0, 128), $d['severity'], '', mb_substr((string) ($d['comment'] ?? ''), 0, 1000));
            $saved++;
        }
        return View::alert('success', "$saved definitions saved");
    }

    public function modal(string $title, string $body, string $button = '', string $action = '', string $color = 'azure'): string
    {
        return $this->app->view->render('partials/modal', compact('title', 'body', 'button', 'action', 'color'));
    }
}
