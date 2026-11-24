<?php

declare(strict_types=1);

namespace Snmptraps\Web;

use Snmptraps\Severity;

/**
 * Renders trap rows / cells as (escaped) HTML for tables, live updates and the JSON feed.
 */
final class TrapPresenter
{
    public const LIST_COLUMNS = [
        'id'       => '',
        'hostname' => 'Hostname',
        'ip'       => 'IP address',
        'date'     => 'Date',
        'message'  => 'Message',
        'severity' => 'Severity',
        'content'  => 'Content',
    ];

    public const DASHBOARD_COLUMNS = [
        'severity' => 'Severity',
        'id'       => '',
        'hostname' => 'Hostname',
        'date'     => 'Date',
        'message'  => 'Message',
    ];

    private const HIDDEN_XS = ['ip', 'content'];

    public function __construct(private readonly bool $stripDomain = false)
    {
    }

    /**
     * @param array<string,string> $columns
     */
    public function headers(array $columns, bool $fullScreen = false): string
    {
        $html = '<thead><tr>';
        foreach ($columns as $k => $label) {
            $hidden = in_array($k, self::HIDDEN_XS, true) ? ' hidden-xs' : '';
            $style  = $k === 'message' ? " data-cell-style='cellStyleMessage'" : '';
            $html  .= "<th class='header-" . e($k) . $hidden . "' data-field='" . e($k) . "'$style>" . e($label) . '</th>';
        }
        if ($fullScreen) {
            $html .= "<th class='header-full_screen disable-sorting' data-field='full_screen'><i class='fa fa-arrows-alt'></i></th>";
        }
        return $html . '</tr></thead>';
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,string> $columns
     */
    public function rows(array $rows, array $columns, bool $fullScreen = false, bool $new = false): string
    {
        if ($rows === []) {
            $span = count($columns) + ($fullScreen ? 1 : 0);
            return "<tr><td colspan='$span'>" . View::alert('info', 'No records found') . '</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $cls = Severity::cssClass((string) $r['severity']) . ($new ? ' new' : '');
            // tooltip.js inserts the title as HTML -> escape the (already escaped) HTML once more
            $html .= "<tr class='$cls tooltip2' data-id='" . (int) $r['id'] . "' title='" . e(e_nl((string) $r['content'])) . "'>";
            foreach ($this->cells($r, array_keys($columns)) as $k => $cell) {
                $hidden = in_array($k, self::HIDDEN_XS, true) ? ' hidden-xs' : '';
                $html  .= "<td class='field-$k$hidden' style='white-space:nowrap'>$cell</td>";
            }
            $html .= ($fullScreen ? '<td></td>' : '') . '</tr>';
        }
        return $html;
    }

    /**
     * @param list<string> $columns
     * @return array<string,string> column => html
     */
    public function cells(array $r, array $columns, bool $single = false): array
    {
        $out = [];
        foreach ($columns as $c) {
            $out[$c] = match ($c) {
                'id'       => $single ? (string) (int) $r['id'] : $this->idButtons((int) $r['id']),
                'hostname' => $this->hostname((string) $r['hostname'], $single),
                'date'     => e($single ? (string) $r['date'] : date('d/m H:i:s', (int) strtotime((string) $r['date']))),
                'message'  => "<strong><a href='message/" . e(b64url_encode((string) $r['message'])) . "/'>" . e($r['message']) . '</a></strong>',
                'severity' => $this->severity((string) $r['severity'], $single),
                'content', 'raw' => e_nl((string) ($r[$c] ?? '')),
                default    => e($r[$c] ?? ''),
            };
        }
        return $out;
    }

    public function severity(string $s, bool $colored = true): string
    {
        return $colored
            ? "<span class='badge badge1 severity alert-" . Severity::cssClass($s) . "'>" . e($s) . '</span>'
            : "<span class='severity badge badge1'>" . e($s) . '</span>';
    }

    public function hostname(string $host, bool $full = false): string
    {
        $label = (!$full && $this->stripDomain && !filter_var($host, FILTER_VALIDATE_IP)) ? explode('.', $host)[0] : $host;
        return "<strong><a href='host/" . e(rawurlencode($host)) . "/'>" . e($label) . '</a></strong>';
    }

    private function idButtons(int $id): string
    {
        return "<div class='btn-group'><a class='btn btn-default btn-xs load-modal-big' href='ajax/trap/?id=$id'><i class='fa fa-eye'></i></a>"
            . "<a class='btn btn-default btn-xs' href='trap/$id/'><i class='fa fa-angle-right' style='width:12px;'></i></a></div>";
    }

    public function actions(int $id): string
    {
        return "<div class='btn-group'>"
            . "<a class='btn btn-default btn-xs load-modal' title='Define severity' href='ajax/message-edit/?action=define&amp;id=$id'><i class='fa fa-cogs'></i></a> "
            . "<a class='btn btn-default btn-xs load-modal' title='Ignore' href='ajax/message-edit/?action=ignore&amp;id=$id'><i class='fa fa-volume-off'></i></a> "
            . "<a class='btn btn-default btn-xs load-modal' title='Delete' href='ajax/message-edit/?action=delete&amp;id=$id'><i class='fa fa-remove'></i></a>"
            . '</div>';
    }
}
