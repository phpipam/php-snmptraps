<?php

declare(strict_types=1);

namespace Snmptraps\Web;

use Snmptraps\Severity;

/**
 * Renders trap rows / cells as escaped HTML (Tabler markup).
 *
 * Tooltips use tippy.js with allowHTML: the tippy-title attribute holds HTML, so the
 * (escaped) content is escaped once more for the attribute.
 */
final class TrapPresenter
{
    /** full list: severity, host, ip, date, message + content */
    public const LIST = ['severity', 'hostname', 'ip', 'date', 'message', 'content'];
    /** compact (dashboard): no ip/content column */
    public const COMPACT = ['severity', 'hostname', 'date', 'message'];

    private const HEADERS = [
        'severity' => ['Severity', 'width:110px'],
        'hostname' => ['Host', 'width:180px'],
        'ip'       => ['IP', 'width:120px'],
        'date'     => ['Date', 'width:150px'],
        'message'  => ['Message', 'width:35%'],
        'content'  => ['Content', ''],
    ];

    public function __construct(
        private readonly bool $stripDomain = false,
        private readonly bool $operator = false,
        private readonly int $repeatWindowMinutes = 10,
    )
    {
    }

    /** @param list<string> $columns */
    public function headers(array $columns): string
    {
        $html = '<thead><tr>';
        foreach ($columns as $c) {
            [$label, $style] = self::HEADERS[$c];
            if ($c === 'message' && !in_array('content', $columns, true)) {
                $style = '';   // message takes the remaining width
            }
            $cls = in_array($c, ['ip', 'content'], true) ? ' class="d-none d-lg-table-cell"' : '';
            $html .= '<th' . $cls . ($style ? " style='$style'" : '') . '>' . e($label) . '</th>';
        }
        return $html . '<th style="width:30px"></th></tr></thead>';
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     */
    public function rows(array $rows, array $columns, bool $new = false): string
    {
        $html = '';
        foreach ($rows as $r) {
            // data-fp: live view drops older rows of the same repeat chain
            $fp = isset($r['fingerprint']) ? " data-fp='" . e($r['fingerprint']) . "'" : '';
            $html .= "<tr data-id='" . (int) $r['id'] . "'$fp" . ($new ? " class='trap-new'" : '') . '>';
            foreach ($columns as $c) {
                $cls = in_array($c, ['ip', 'content'], true) ? ' d-none d-lg-table-cell' : '';
                $html .= "<td class='td-$c$cls'>" . $this->cell($r, $c) . '</td>';
            }
            $html .= '<td class="text-end">' . $this->actions($r) . '</td></tr>';
        }
        return $html;
    }

    public function cell(array $r, string $c): string
    {
        return match ($c) {
            'severity' => $this->severity((string) $r['severity']),
            'hostname' => $this->hostname((string) $r['hostname']),
            'ip'       => '<span class="text-secondary">' . e($r['ip']) . '</span>',
            'date'     => '<span class="text-secondary">' . e(date('d.m.Y H:i:s', (int) strtotime((string) $r['date']))) . '</span>',
            // "recent" dot before the (clipped) message
            'message'  => '<div class="d-flex align-items-center">' . $this->recentDot($r) . '<div class="text-truncate">' . $this->message($r) . '</div>' . $this->repeatBadge($r) . '</div>',
            'content'  => '<span class="text-secondary">' . $this->oneLine((string) $r['content']) . '</span>',
            default    => e($r[$c] ?? ''),
        };
    }

    /** content lines joined on one line (cells are clipped with an ellipsis) */
    private function oneLine(string $text): string
    {
        $lines = array_filter(preg_split('/\r\n|\r|\n/', $text) ?: [], static fn ($l) => trim($l) !== '');
        return implode('<span class="text-muted mx-1">&middot;</span>', array_map('e', $lines));
    }

    public function severity(string $s): string
    {
        $color = Severity::tablerColor($s);
        return "<span class='badge bg-info-lt text-$color w-100'>" . e($s) . '</span>';
    }

    /** traps received within this many seconds get a pulsing dot before the message */
    public const RECENT_SECONDS = 300;

    /** animated status dot for traps from the last RECENT_SECONDS */
    public function recentDot(array $r): string
    {
        $ts = strtotime((string) ($r['date'] ?? ''));
        if ($ts === false || $ts < time() - self::RECENT_SECONDS) {
            return '';
        }
        $color = Severity::tablerColor((string) $r['severity']);
        return "<span class='status-dot status-dot-animated bg-$color d-block flex-shrink-0 me-2' data-toggle='tooltip-nofollow' tippy-title='Received in the last 5 minutes'></span>";
    }

    /**
     * Repeat badge: number of repeats (identical traps, each within repeat_window_minutes of the
     * previous one); the tooltip shows the first and the last occurrence.
     */
    public function repeatBadge(array $r): string
    {
        $n = (int) ($r['repeats'] ?? 0);
        if ($n < 1) {
            return '';
        }
        $fmt = static fn (?string $d): string => $d ? date('d.m.Y H:i:s', (int) strtotime($d)) : '?';
        $tip = '<strong>Repeated ' . $n . ' time' . ($n === 1 ? '' : 's') . '</strong> (' . ($n + 1) . ' identical traps)<hr>'
            . 'First: ' . $fmt($r['first_date'] ?? null) . '<br>Last: ' . $fmt((string) $r['date'])
            . '<hr><span class="text-secondary">same host, OID and content, each less than ' . $this->repeatWindowMinutes . ' min after the previous one</span>';
        return "<span class='badge bg-secondary-lt text-secondary flex-shrink-0 ms-2' data-toggle='tooltip' tippy-title='" . e($tip) . "'>"
            . "<i class='ti ti-repeat me-1'></i>" . $n . '</span>';
    }

    public function hostname(string $host, bool $full = false): string
    {
        $label = (!$full && $this->stripDomain && !filter_var($host, FILTER_VALIDATE_IP)) ? explode('.', $host)[0] : $host;
        $tip = $label !== $host ? $this->tip(e($host)) : '';
        return "<a href='host/" . e(rawurlencode($host)) . "/' class='text-reset text-nowrap'$tip>" . e($label) . '</a>';
    }

    public function message(array $r): string
    {
        $color = Severity::tablerColor((string) $r['severity']);
        $tip = $this->tip('<strong>' . e($r['oid']) . '</strong><hr>' . e_nl((string) $r['content']));
        return "<a href='message/" . e(b64url_encode((string) $r['message'])) . "/' class='text-$color fw-bold'$tip>" . e($r['message']) . '</a>';
    }

    /** tippy tooltip attributes for trusted (already escaped) HTML */
    public function tip(string $html): string
    {
        return " data-toggle='tooltip' tippy-title='" . e($html) . "'";
    }

    /** Row actions dropdown */
    public function actions(array $r): string
    {
        $id = (int) $r['id'];
        $html = '<span class="dropdown">'
            . '<a class="dropdown-toggle text-secondary" href="#" data-bs-toggle="dropdown" data-bs-popper-config=\'{"strategy":"fixed"}\' aria-expanded="false"></a>'
            . '<div class="dropdown-menu dropdown-menu-end">'
            . '<h6 class="dropdown-header">Trap</h6>'
            . "<a class='dropdown-item load-modal' href='ajax/trap/?id=$id'><i class='ti ti-eye me-2'></i>Quick view</a>"
            . "<a class='dropdown-item' href='trap/$id/'><i class='ti ti-file-description me-2'></i>Details</a>"
            . "<a class='dropdown-item' href='message/" . e(b64url_encode((string) $r['message'])) . "/'><i class='ti ti-list-search me-2'></i>All traps with this message</a>";
        if ($this->operator) {
            $html .= '<div class="dropdown-divider"></div><h6 class="dropdown-header">Manage</h6>'
                . "<a class='dropdown-item load-modal' href='ajax/message-edit/?action=define&amp;id=$id'><i class='ti ti-adjustments me-2'></i>Define severity</a>"
                . "<a class='dropdown-item load-modal' href='ajax/message-edit/?action=ignore&amp;id=$id'><i class='ti ti-volume-off me-2'></i>Ignore</a>"
                . "<a class='dropdown-item text-red load-modal' href='ajax/message-edit/?action=delete&amp;id=$id'><i class='ti ti-trash me-2'></i>Delete traps</a>";
        }
        return $html . '</div></span>';
    }
}
