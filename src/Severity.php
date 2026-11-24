<?php

declare(strict_types=1);

namespace Snmptraps;

/**
 * Severity levels and their groupings / presentation.
 */
final class Severity
{
    /** Syslog-style levels, most severe first */
    public const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'informational', 'debug'];

    public const UNKNOWN = 'unknown';

    /** Dashboard / menu groups. Keys are URL slugs (kept for link compatibility). */
    public const GROUPS = [
        'major'         => ['label' => 'Major',   'title' => 'Emergency, Critical and Alert events',     'levels' => ['emergency', 'alert', 'critical']],
        'minor'         => ['label' => 'Medium',  'title' => 'Error and Warning events',                  'levels' => ['error', 'warning']],
        'informational' => ['label' => 'Minor',   'title' => 'Informational, Notice and Debug events',    'levels' => ['notice', 'informational', 'debug']],
        'unknown'       => ['label' => 'Unknown', 'title' => 'Unknown severity events',                   'levels' => ['unknown']],
    ];

    /** Common vendor synonyms mapped to syslog levels */
    private const ALIASES = [
        'emerg' => 'emergency', 'crit' => 'critical', 'err' => 'error', 'warn' => 'warning',
        'info' => 'informational', 'information' => 'informational', 'major' => 'critical', 'minor' => 'warning',
        'cleared' => 'notice', 'clear' => 'notice', 'indeterminate' => 'unknown',
    ];

    /** @return list<string> all levels including "unknown" */
    public static function all(): array
    {
        return [...self::LEVELS, self::UNKNOWN];
    }

    public static function isValid(string $s, bool $allowUnknown = false): bool
    {
        return in_array($s, $allowUnknown ? self::all() : self::LEVELS, true);
    }

    /**
     * Normalise a free-form severity string found inside a trap.
     */
    public static function normalize(string $s): string
    {
        $s = strtolower(trim($s, " \t\"'"));
        // strip numeric suffixes like "critical(2)"
        $s = preg_replace('/\(\d+\)$/', '', $s) ?? $s;
        $s = self::ALIASES[$s] ?? $s;
        return in_array($s, self::LEVELS, true) ? $s : self::UNKNOWN;
    }

    /** Bootstrap contextual class */
    public static function cssClass(string $s): string
    {
        return match (true) {
            in_array($s, self::GROUPS['major']['levels'], true)         => 'danger',
            in_array($s, self::GROUPS['minor']['levels'], true)         => 'warning',
            in_array($s, self::GROUPS['informational']['levels'], true) => 'success',
            default                                                    => 'info',
        };
    }

    public static function pushoverPriority(string $s): int
    {
        return match ($s) {
            'emergency'                    => 2,
            'alert', 'critical'            => 1,
            'notice'                       => -1,
            'informational', 'debug'       => -2,
            default                        => 0,
        };
    }

    public static function color(string $s): string
    {
        return match (self::cssClass($s)) {
            'danger'  => '#A7254E',
            'warning' => '#D66300',
            'success' => '#66AA00',
            default   => '#3366CC',
        };
    }
}
