<?php

declare(strict_types=1);

namespace Snmptraps\Notify;

use RuntimeException;
use Snmptraps\Config;
use Snmptraps\Severity;

/**
 * Slack / Mattermost incoming webhook (URL = params url . key).
 */
final class SlackChannel implements Channel
{
    private string $webhook;
    private string $channel;
    private string $mentions;
    private string $username;

    public function __construct(Config $config)
    {
        $p = $config->notificationParams['slack'] ?? [];
        $this->webhook = (string) ($p['url'] ?? '') . (string) ($p['key'] ?? '');
        if (!preg_match('#^https?://#', $this->webhook)) {
            throw new RuntimeException('Invalid slack parameters (url required)');
        }
        $this->channel  = (string) ($p['channel'] ?? '');
        $this->mentions = implode(' ', array_map('strval', (array) ($p['mentions'] ?? [])));
        $this->username = (string) ($p['username'] ?? '') ?: 'php-snmptraps';
    }

    public function send(Message $message, array $users): void
    {
        $trap  = $message->trap;
        $title = "[{$trap->severity}] {$trap->hostname} {$trap->message}";
        $lines = $message->contentLines();

        $payload = [
            'username'    => $this->username,
            'text'        => trim($this->mentions . ' new snmp trap received'),
            'attachments' => [[
                'fallback'   => trim($this->mentions . ' ' . $title . "\n" . implode("\n", $lines)),
                'color'      => Severity::color($trap->severity),
                'title'      => $title,
                'title_link' => $message->trapUrl(),
                'text'       => $lines === [] ? '' : '* ' . implode("\n* ", $lines),
                'footer'     => $trap->oid,
            ]],
        ];
        if ($this->channel !== '') {
            $payload['channel'] = $this->channel;
        }

        $res = HttpClient::postJson($this->webhook, $payload);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            throw new RuntimeException("Webhook returned HTTP {$res['status']}: " . mb_substr($res['body'], 0, 200));
        }
    }
}
