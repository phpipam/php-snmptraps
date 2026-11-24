<?php

declare(strict_types=1);

namespace Snmptraps\Notify;

use Snmptraps\Config;

/**
 * A notification transport. To add one: implement this interface, register it in
 * Notifier::CHANNELS, add its name to $notification_methods and its settings to
 * $notification_params[name] in config.php.
 */
interface Channel
{
    public function __construct(Config $config);

    /**
     * @param list<array<string,mixed>> $users recipient rows from the users table
     * @throws \RuntimeException on delivery failure
     */
    public function send(Message $message, array $users): void;
}
