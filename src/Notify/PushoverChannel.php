<?php

declare(strict_types=1);

namespace Snmptraps\Notify;

use RuntimeException;
use Snmptraps\Config;
use Snmptraps\Severity;

/**
 * Pushover (https://pushover.net). Sends one message to the configured user/group key.
 */
final class PushoverChannel implements Channel
{
    private const API = 'https://api.pushover.net/1/messages.json';

    private string $token;
    private string $key;

    public function __construct(Config $config)
    {
        $p = $config->notificationParams['pushover'] ?? [];
        $this->token = (string) ($p['token'] ?? '');
        $this->key   = (string) ($p['key'] ?? '');
        if ($this->token === '' || $this->key === '') {
            throw new RuntimeException('Invalid pushover parameters (token and key required)');
        }
    }

    public function send(Message $message, array $users): void
    {
        $trap = $message->trap;
        $priority = Severity::pushoverPriority($trap->severity);
        $fields = [
            'token'    => $this->token,
            'user'     => $this->key,
            'title'    => mb_substr("{$trap->hostname} [{$trap->severity}] {$trap->message}", 0, 250),
            'message'  => mb_substr(implode("\n", $message->contentLines()) ?: $trap->message, 0, 1024),
            'priority' => $priority,
        ];
        if ($message->trapUrl() !== '') {
            $fields['url'] = $message->trapUrl();
        }
        if ($priority === 2) {
            // emergency priority is rejected by the API without retry/expire
            $fields['retry']  = 60;
            $fields['expire'] = 3600;
        }

        $res = HttpClient::postForm(self::API, $fields);
        if ($res['status'] !== 200) {
            throw new RuntimeException("Pushover API returned HTTP {$res['status']}: " . mb_substr($res['body'], 0, 200));
        }
    }
}
