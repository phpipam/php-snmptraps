<?php

declare(strict_types=1);

namespace Snmptraps\Web\Controller;

use PDOException;
use Snmptraps\Auth;
use Snmptraps\Notify\Notifier;
use Snmptraps\Severity;
use Snmptraps\Web\App;
use Snmptraps\Web\HttpException;
use Snmptraps\Web\View;

/**
 * Administration pages and the generic add/edit/delete modal for settings tables,
 * plus the user's own profile.
 */
final class Settings
{
    public const PAGES = [
        'severity_definitions' => 'Severity definitions',
        'exceptions'           => 'Ignored messages',
        'maintenance'          => 'Maintenance',
        'mibs'                 => 'MIB files',
        'users'                => 'User management',
        'general'              => 'General settings',
    ];

    private const ITEM_NAMES = [
        'severity_definitions' => 'severity definition',
        'exceptions'           => 'ignore rule',
        'maintenance'          => 'maintenance window',
        'users'                => 'user',
    ];

    public function __construct(private readonly App $app)
    {
    }

    /**
     * Field definitions per editable table.
     * type: text|textarea|select|checkboxes|datetime|time|int|password
     *
     * @return array<string, array<string, array<string,mixed>>>
     */
    public function tables(): array
    {
        $hosts = $this->app->traps()->hosts();
        $hostOptions = array_combine($hosts, $hosts);
        $levels = array_combine(Severity::LEVELS, Severity::LEVELS);
        $methods = Notifier::enabledMethods($this->app->config);

        return [
            'severity_definitions' => [
                'oid'      => ['label' => 'OID', 'type' => 'text', 'required' => true, 'max' => 128, 'help' => 'e.g. IF-MIB::linkDown - matches every trap OID containing this text'],
                'severity' => ['label' => 'Severity', 'type' => 'select', 'options' => $levels, 'required' => true],
                'content'  => ['label' => 'Message', 'type' => 'text', 'max' => 128, 'help' => 'Optional - limit re-classification of stored traps to this exact message'],
                'comment'  => ['label' => 'Comment', 'type' => 'textarea'],
            ],
            'exceptions' => [
                'oid'      => ['label' => 'OID', 'type' => 'text', 'required' => true, 'max' => 128],
                'hostname' => ['label' => 'Hostname', 'type' => 'text', 'datalist' => ['all', ...$hosts], 'required' => true, 'max' => 128, 'help' => '"all" or a hostname'],
                'content'  => ['label' => 'Content', 'type' => 'text', 'max' => 128, 'help' => 'Optional - only ignore traps whose content contains this text'],
                'comment'  => ['label' => 'Comment', 'type' => 'textarea'],
            ],
            'maintenance' => [
                'hostname' => ['label' => 'Hostname', 'type' => 'text', 'datalist' => $hosts, 'required' => true, 'max' => 128],
                'start'    => ['label' => 'Start', 'type' => 'datetime', 'required' => true],
                'stop'     => ['label' => 'Stop', 'type' => 'datetime', 'required' => true],
                'comment'  => ['label' => 'Comment', 'type' => 'textarea'],
            ],
            'users' => [
                'username'                => ['label' => 'Username', 'type' => 'text', 'required' => true, 'max' => 64, 'pattern' => '/^[\w.@\\\\-]+$/u'],
                'real_name'               => ['label' => 'Real name', 'type' => 'text', 'required' => true, 'max' => 128],
                'auth_method'             => ['label' => 'Authentication', 'type' => 'select', 'options' => array_combine(Auth::AUTH_METHODS, Auth::AUTH_METHODS), 'required' => true],
                'password'                => ['label' => 'Password', 'type' => 'password', 'help' => 'Local users only, min 8 characters. Leave empty to keep current password.'],
                'role'                    => ['label' => 'Role', 'type' => 'select', 'options' => array_combine(Auth::ROLES, Auth::ROLES), 'required' => true],
                'email'                   => ['label' => 'E-mail', 'type' => 'text', 'max' => 128, 'validate' => FILTER_VALIDATE_EMAIL],
                'tel'                     => ['label' => 'Phone', 'type' => 'text', 'max' => 32, 'pattern' => '/^\+?[0-9 \-\/]+$/'],
                'notification_types'      => ['label' => 'Notification types', 'type' => 'checkboxes', 'options' => array_combine($methods, $methods), 'empty' => 'none'],
                'notification_severities' => ['label' => 'Notify on severities', 'type' => 'checkboxes', 'options' => array_combine(Severity::all(), Severity::all()), 'empty' => 'none'],
                'quiet_time_start'        => ['label' => 'Quiet time start', 'list' => false, 'type' => 'time', 'help' => 'No notifications between start and stop. Same value = no quiet time.'],
                'quiet_time_stop'         => ['label' => 'Quiet time stop', 'list' => false, 'type' => 'time'],
                'reload_page'             => ['label' => 'Page reload (s)', 'list' => false, 'type' => 'int', 'min' => 0, 'max' => 86400, 'help' => '0 = disabled'],
                'dash_layout'             => ['label' => 'Dashboard layout', 'list' => false, 'type' => 'text', 'max' => 128, 'pattern' => '/^\d{1,2}:\d{1,3}(;\d{1,2}:\d{1,3}){3}$/', 'help' => 'width:rows for major;medium;minor;unknown widgets, e.g. 6:30;6:30;12:15;12:15 (rows 0 hides a widget)'],
                'hostnames'               => ['label' => 'Permitted hosts', 'type' => 'checkboxes', 'options' => $hostOptions, 'empty' => 'all', 'keep_existing' => true, 'help' => 'None selected = all hosts. Ignored for administrators.'],
            ],
        ];
    }

    /** Fields a user may change on their own profile */
    private const PROFILE_FIELDS = ['real_name', 'password', 'email', 'tel', 'notification_types', 'notification_severities', 'quiet_time_start', 'quiet_time_stop', 'reload_page', 'dash_layout'];

    public function page(string $page): string
    {
        $this->app->requireAdmin();
        if (!isset(self::PAGES[$page])) {
            throw new HttpException(404, 'Invalid settings page');
        }
        $vars = ['page' => $page, 'pages' => self::PAGES];

        switch ($page) {
            case 'mibs':
                $vars['mib'] = $this->app->mib();
                $vars['files'] = $vars['mib']->files();
                break;
            case 'general':
                $vars['config'] = $this->app->config;
                break;
            default:
                $order = ['severity_definitions' => '`oid`', 'exceptions' => '`id` DESC', 'maintenance' => '`start` DESC', 'users' => '`username`'][$page];
                $vars['fields'] = $this->tables()[$page];
                $vars['rows'] = $this->app->db->all("SELECT * FROM `$page` ORDER BY $order");
        }
        return $this->app->view->render('settings', $vars);
    }

    public function itemForm(): string
    {
        $this->app->requireAdmin();
        [$table, $action, $id] = [$this->app->input('table'), $this->app->input('action'), (int) $this->app->input('id')];
        $tables = $this->tables();
        if (!isset($tables[$table]) || !in_array($action, ['add', 'edit', 'delete'], true)) {
            throw new HttpException(400, 'Invalid request');
        }
        $row = [];
        if ($action !== 'add') {
            $row = $this->app->db->find($table, $id) ?? throw new HttpException(404, 'Invalid item');
        }
        unset($row['password']);

        $body = $this->app->view->render('partials/item-form', [
            'table'  => $table,
            'action' => $action,
            'id'     => $id,
            'fields' => $tables[$table],
            'row'    => $row,
        ]);
        $title = ['add' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'][$action] . ' ' . self::ITEM_NAMES[$table];
        $button = ['add' => 'Create', 'edit' => 'Save', 'delete' => 'Delete'][$action];
        return (new Ajax($this->app))->modal($title, $body, $button, 'ajax/item/', $action === 'delete' ? 'red' : 'azure');
    }

    public function itemSubmit(): string
    {
        $this->app->requireAdmin();
        [$table, $action, $id] = [$this->app->input('table'), $this->app->input('action'), (int) $this->app->input('id')];
        $tables = $this->tables();
        if (!isset($tables[$table]) || !in_array($action, ['add', 'edit', 'delete'], true)) {
            return View::alert('danger', 'Invalid request');
        }
        $db = $this->app->db;

        if ($action === 'delete') {
            if ($table === 'users' && $id === $this->app->auth->userId()) {
                return View::alert('danger', 'You cannot delete yourself');
            }
            return $db->delete($table, $id) > 0 ? View::alert('success', 'Item removed') : View::alert('danger', 'Item not found');
        }

        $existing = $action === 'edit' ? $db->find($table, $id) : null;
        if ($action === 'edit' && $existing === null) {
            return View::alert('danger', 'Item not found');
        }

        [$values, $errors] = $this->validate($tables[$table], $_POST, $action === 'add', $existing);

        if ($table === 'maintenance' && isset($values['start'], $values['stop']) && $values['start'] >= $values['stop']) {
            $errors[] = 'Stop must be after start';
        }
        if ($table === 'users') {
            $this->userRules($values, $errors, $action === 'add', $existing);
        }
        if ($errors !== []) {
            return View::alert('danger', implode('<br>', array_map('e', $errors)));
        }

        try {
            if ($action === 'add') {
                $db->insert($table, $values);
                return View::alert('success', 'Item created');
            }
            $db->update($table, $id, $values);
            return View::alert('success', 'Item updated');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return View::alert('danger', 'Duplicate item');
            }
            throw $e;
        }
    }

    public function profileForm(): string
    {
        $user = $this->app->auth->user();
        $fields = array_intersect_key($this->tables()['users'], array_flip(self::PROFILE_FIELDS));
        if ($user['auth_method'] !== 'local') {
            unset($fields['password']);
        }
        unset($user['password']);
        $body = $this->app->view->render('partials/item-form', [
            'table' => 'profile', 'action' => 'edit', 'id' => 0, 'fields' => $fields, 'row' => $user,
        ]);
        return (new Ajax($this->app))->modal('Edit your profile', $body, 'Save', 'ajax/profile/');
    }

    public function profileSubmit(): string
    {
        $user = $this->app->auth->user();
        $fields = array_intersect_key($this->tables()['users'], array_flip(self::PROFILE_FIELDS));
        if ($user['auth_method'] !== 'local') {
            unset($fields['password']);
        }
        [$values, $errors] = $this->validate($fields, $_POST, false);
        if (isset($values['password'])) {
            $values['password'] = Auth::hashPassword($values['password']);
        }
        if ($errors !== []) {
            return View::alert('danger', implode('<br>', array_map('e', $errors)));
        }
        $this->app->db->update('users', (int) $user['id'], $values);
        return View::alert('success', 'Profile updated');
    }

    private function userRules(array &$values, array &$errors, bool $isAdd, ?array $existing): void
    {
        $method = $values['auth_method'] ?? ($existing['auth_method'] ?? 'local');
        if ($method !== 'local') {
            unset($values['password']);
        } elseif (isset($values['password'])) {
            $values['password'] = Auth::hashPassword($values['password']);
        } elseif ($isAdd || trim((string) ($existing['password'] ?? '')) === '') {
            $errors[] = 'Password is required for local users';
        }
        if (($values['role'] ?? '') === 'administrator') {
            $values['hostnames'] = 'all';
        }
        if ($isAdd) {
            $values += ['reload_page' => 900, 'dash_layout' => '6:30;6:30;12:15;12:15', 'hostnames' => 'all', 'notification_types' => 'none', 'notification_severities' => 'none'];
        }
        // never lock yourself out
        if ($existing !== null && (int) $existing['id'] === $this->app->auth->userId() && ($values['role'] ?? 'administrator') !== 'administrator') {
            $errors[] = 'You cannot remove your own administrator role';
        }
    }

    /**
     * @param array<string, array<string,mixed>> $fields
     * @return array{0: array<string,mixed>, 1: list<string>}
     */
    private function validate(array $fields, array $input, bool $isAdd, ?array $existing = null): array
    {
        $values = [];
        $errors = [];
        foreach ($fields as $name => $f) {
            $label = $f['label'];
            if ($f['type'] === 'checkboxes') {
                $allowed = array_map('strval', array_keys($f['options']));
                if (!empty($f['keep_existing']) && $existing !== null) {
                    $allowed = [...$allowed, ...explode(';', (string) ($existing[$name] ?? ''))];
                }
                $sel = array_values(array_unique(array_intersect(array_map('strval', (array) ($input[$name] ?? [])), $allowed)));
                $values[$name] = $sel === [] ? $f['empty'] : implode(';', $sel);
                continue;
            }
            $raw = $input[$name] ?? '';
            if (!is_string($raw)) {
                $errors[] = "$label: invalid value";
                continue;
            }
            $v = $f['type'] === 'password' ? $raw : trim($raw);

            if ($v === '') {
                if (!empty($f['required'])) {
                    $errors[] = "$label is required";
                } elseif ($f['type'] !== 'password') {
                    $values[$name] = in_array($f['type'], ['time'], true) ? '00:00:00' : ($f['type'] === 'int' ? 0 : null);
                }
                continue;
            }

            switch ($f['type']) {
                case 'select':
                    if (!array_key_exists($v, $f['options'])) {
                        $errors[] = "$label: invalid option";
                        continue 2;
                    }
                    break;
                case 'datetime':
                    $d = Pages::parseDateTime($v);
                    if ($d === null) {
                        $errors[] = "$label: invalid date/time";
                        continue 2;
                    }
                    $v = $d->format('Y-m-d H:i:s');
                    break;
                case 'time':
                    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $v)) {
                        $errors[] = "$label: invalid time";
                        continue 2;
                    }
                    $v = strlen($v) === 5 ? "$v:00" : $v;
                    break;
                case 'int':
                    if (!ctype_digit($v) || (int) $v < ($f['min'] ?? 0) || (int) $v > ($f['max'] ?? PHP_INT_MAX)) {
                        $errors[] = "$label: invalid number";
                        continue 2;
                    }
                    $v = (int) $v;
                    break;
                case 'password':
                    if (mb_strlen($v) < 8) {
                        $errors[] = "$label: minimum 8 characters";
                        continue 2;
                    }
                    break;
            }
            if (is_string($v) && isset($f['max']) && mb_strlen($v) > $f['max']) {
                $errors[] = "$label: maximum {$f['max']} characters";
                continue;
            }
            if (is_string($v) && isset($f['pattern']) && !preg_match($f['pattern'], $v)) {
                $errors[] = "$label: invalid format";
                continue;
            }
            if (isset($f['validate']) && filter_var($v, $f['validate']) === false) {
                $errors[] = "$label: invalid value";
                continue;
            }
            $values[$name] = $v;
        }
        return [$values, $errors];
    }
}
