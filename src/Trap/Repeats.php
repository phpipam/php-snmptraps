<?php

declare(strict_types=1);

namespace Snmptraps\Trap;

use Snmptraps\Config;

/**
 * Repeated traps: identical traps (same hostname, OID and content, ignoring timestamps)
 * form a chain while each one arrives less than $repeat_window_minutes after the previous.
 *
 * Only the latest trap of a chain is shown in the UI (older ones get superseded = 1);
 * it carries the chain length (repeats) and the date of the first trap (first_date).
 * Notifications are skipped when an identical trap arrived within $notification_dedup_minutes.
 *
 * Used by TrapProcessor (one trap at a time) and bin/rebuild-repeats.php (bulk).
 */
final class Repeats
{
    /** SNMP TimeTicks rendering, e.g. 39:11:52:53.07 (sysUpTime based timestamps) */
    private const TIMETICKS = '/^\d+:\d{1,2}:\d{2}:\d{2}(\.\d+)?$/';
    /** varbind names that are timestamps by convention, e.g. clogHistTimestamp */
    private const TIME_NAMES = '/(timestamp|lastchanged)$/i';

    public function __construct(private readonly int $dedupMinutes, private readonly int $windowMinutes)
    {
    }

    public static function fromConfig(Config $config): self
    {
        return new self($config->notificationDedupMinutes, $config->repeatWindowMinutes);
    }

    public function enabled(): bool
    {
        return max($this->dedupMinutes, $this->windowMinutes) > 0;
    }

    /** minutes to look back for the previous identical trap */
    public function lookbackMinutes(): int
    {
        return max($this->dedupMinutes, $this->windowMinutes);
    }

    /**
     * Comparison key: md5 of hostname, OID and content with timestamp values blanked,
     * so e.g. Cisco syslog traps (clogHistTimestamp) can repeat.
     */
    public static function fingerprint(string $hostname, string $oid, string $content): string
    {
        return md5($hostname . "\n" . $oid . "\n" . self::normalize($content));
    }

    public static function normalize(string $content): string
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $parts = explode(' => ', $line, 2);
            if (count($parts) === 2 && (preg_match(self::TIME_NAMES, trim($parts[0])) || preg_match(self::TIMETICKS, trim($parts[1])))) {
                $line = $parts[0] . ' => *';
            }
            $out[] = $line;
        }
        return implode("\n", $out);
    }

    /**
     * Decide repeat state of a new trap from the previous identical one.
     *
     * @param array{id:int|string, repeats:int|string, first_date:?string, date:string}|null $prev
     * @param int $age seconds between the previous and the new trap
     * @return array{repeats:int, first_date:?string, supersedes:?int, duplicate:bool}
     */
    public function evaluate(?array $prev, int $age): array
    {
        if ($prev === null) {
            return ['repeats' => 0, 'first_date' => null, 'supersedes' => null, 'duplicate' => false];
        }
        $repeat = $this->windowMinutes > 0 && $age < $this->windowMinutes * 60;
        return [
            'repeats'    => $repeat ? (int) $prev['repeats'] + 1 : 0,
            'first_date' => $repeat ? ($prev['first_date'] ?? $prev['date']) : null,
            'supersedes' => $repeat ? (int) $prev['id'] : null,
            'duplicate'  => $this->dedupMinutes > 0 && $age < $this->dedupMinutes * 60,
        ];
    }
}
