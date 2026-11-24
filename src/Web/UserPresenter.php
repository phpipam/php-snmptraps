<?php

declare(strict_types=1);

namespace Snmptraps\Web;

use Snmptraps\Notify\Notifier;

/**
 * Small HTML snippets for the user management pages (all output escaped).
 */
final class UserPresenter
{
    private const ROLE_COLORS = ['administrator' => 'red', 'operator' => 'orange', 'user' => 'azure'];
    private const AUTH_LABELS = ['local' => 'Local', 'ad' => 'Active Directory'];

    public static function role(string $role): string
    {
        $color = self::ROLE_COLORS[$role] ?? 'secondary';
        return "<span class='badge badge-outline text-$color'>" . e(ucfirst($role)) . '</span>';
    }

    public static function auth(string $method): string
    {
        $icon = $method === 'ad' ? 'ti-building' : 'ti-key';
        return "<i class='ti $icon me-1'></i>" . e(self::AUTH_LABELS[$method] ?? $method);
    }

    /** "22:00 - 08:00" or null when there is no quiet time */
    public static function quietTime(array $u): ?string
    {
        $start = substr((string) ($u['quiet_time_start'] ?? ''), 0, 5);
        $stop = substr((string) ($u['quiet_time_stop'] ?? ''), 0, 5);
        return $start === $stop ? null : "$start - $stop";
    }

    public static function inQuietTime(array $u): bool
    {
        return Notifier::inQuietTime(date('H:i:s'), (string) ($u['quiet_time_start'] ?? ''), (string) ($u['quiet_time_stop'] ?? ''));
    }

    /** @return list<string> values of a ";"-separated column, without the "none"/"all" markers */
    public static function values(?string $value): array
    {
        return array_values(array_filter(explode(';', (string) $value), static fn ($x) => $x !== '' && $x !== 'none' && $x !== 'all'));
    }

    /** notification methods the user has selected (only the ones enabled in config are effective) */
    public static function methods(array $u, array $enabled): string
    {
        $out = '';
        foreach (self::values($u['notification_types'] ?? '') as $m) {
            $on = in_array($m, $enabled, true);
            $out .= '<span class="badge badge-outline ' . ($on ? 'text-green' : 'text-secondary text-decoration-line-through') . ' me-1"'
                . ($on ? '' : " data-toggle='tooltip-nofollow' tippy-title='Not enabled in config.php'") . '>' . e($m) . '</span>';
        }
        return $out !== '' ? $out : '<span class="text-secondary">none</span>';
    }

    /** all permitted hosts? (administrators always see all hosts) */
    public static function allHosts(array $u): bool
    {
        return ($u['role'] ?? '') === 'administrator' || self::values($u['hostnames'] ?? '') === [];
    }
}
