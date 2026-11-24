<?php

declare(strict_types=1);

namespace Snmptraps\Notify;

use DateTimeImmutable;
use Snmptraps\Config;
use Snmptraps\Database;
use Snmptraps\Logger;
use Snmptraps\Trap\Trap;
use Throwable;

/**
 * Selects recipients for a stored trap and dispatches it to the enabled channels.
 *
 * A user is notified when:
 *  - the trap severity is in the user's notification_severities
 *  - the trap host is in the user's hostnames (or hostnames = "all")
 *  - current time is outside the user's quiet hours (start == stop means no quiet hours)
 *  - the host is not in an active maintenance window
 */
final class Notifier
{
    /** config method name => channel class */
    public const CHANNELS = [
        'mail'     => MailChannel::class,
        'sms'      => SmsChannel::class,
        'pushover' => PushoverChannel::class,
        'slack'    => SlackChannel::class,
    ];

    public function __construct(
        private readonly Config $config,
        private readonly Database $db,
        private readonly Logger $log,
        private readonly ?DateTimeImmutable $now = null,
    ) {
    }

    /** @return list<string> channels enabled in config.php that have an implementation */
    public static function enabledMethods(Config $config): array
    {
        return array_values(array_filter($config->notificationMethods, static fn ($m) => isset(self::CHANNELS[$m])));
    }

    public function notify(Trap $trap, int $trapId): void
    {
        $enabled = self::enabledMethods($this->config);
        if ($enabled === []) {
            return;
        }
        if ($this->inMaintenance($trap->hostname)) {
            return;
        }

        $byMethod = [];
        foreach ($this->recipients($trap) as $user) {
            foreach (explode(';', (string) $user['notification_types']) as $m) {
                if (in_array($m, $enabled, true)) {
                    $byMethod[$m][] = $user;
                }
            }
        }

        $message = new Message($trap, $trapId, $this->config->url);
        foreach ($byMethod as $method => $users) {
            try {
                $class = self::CHANNELS[$method];
                /** @var Channel $channel */
                $channel = new $class($this->config);
                $channel->send($message, $users);
            } catch (Throwable $e) {
                $this->log->error("$method notification for {$trap->hostname} failed: " . $e->getMessage());
            }
        }
    }

    private function inMaintenance(string $hostname): bool
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM `maintenance` WHERE `hostname` = ? AND NOW() BETWEEN `start` AND `stop`',
            [$hostname]
        ) > 0;
    }

    /** @return list<array<string,mixed>> */
    private function recipients(Trap $trap): array
    {
        $users = $this->db->all(
            "SELECT * FROM `users` WHERE FIND_IN_SET(?, REPLACE(`notification_severities`, ';', ',')) > 0
               AND `notification_types` <> 'none' AND `notification_types` <> ''",
            [$trap->severity]
        );
        $now = ($this->now ?? new DateTimeImmutable())->format('H:i:s');

        return array_values(array_filter($users, static function (array $u) use ($trap, $now): bool {
            $hosts = (string) ($u['hostnames'] ?? 'all');
            if ($hosts !== 'all' && $hosts !== '' && !in_array($trap->hostname, explode(';', $hosts), true)) {
                return false;
            }
            return !self::inQuietTime($now, (string) ($u['quiet_time_start'] ?? ''), (string) ($u['quiet_time_stop'] ?? ''));
        }));
    }

    /**
     * Quiet-time window check on "HH:MM:SS" strings; windows may wrap midnight.
     */
    public static function inQuietTime(string $now, string $start, string $stop): bool
    {
        if ($start === '' || $stop === '' || $start === $stop) {
            return false;
        }
        return $start < $stop
            ? ($now >= $start && $now < $stop)
            : ($now >= $start || $now < $stop);
    }
}
