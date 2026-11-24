<?php

declare(strict_types=1);

namespace Snmptraps\Notify;

use RuntimeException;
use Snmptraps\Config;

/**
 * SMS through an HTTP gateway:
 *   GET http://{server}{uri}sendsms?sender=&recipient=&appid=&reference=&message=
 * expecting JSON {"SendSmsResponse":{"status":"OK"}}.
 */
final class SmsChannel implements Channel
{
    private array $p;

    public function __construct(Config $config)
    {
        $p = $config->notificationParams['sms'] ?? null;
        if (!is_array($p) || empty($p['server'])) {
            throw new RuntimeException('Invalid SMS parameters');
        }
        $this->p = $p;
    }

    public function send(Message $message, array $users): void
    {
        $trap = $message->trap;
        $text = "[{$trap->hostname}] - {$trap->message} ({$trap->severity}) \n\n" . implode("\n", $message->contentLines());
        $len = (int) ($this->p['length'] ?? 0);
        if ($len > 0) {
            $text = mb_substr($text, 0, $len);
        }

        $errors = [];
        foreach ($users as $u) {
            $tel = str_replace(['+', ' ', '-', '/'], '', (string) ($u['tel'] ?? ''));
            if ($tel === '' || !ctype_digit($tel)) {
                $errors[] = "invalid phone number for {$u['username']}";
                continue;
            }
            try {
                $this->sendOne($tel, $text);
            } catch (RuntimeException $e) {
                $errors[] = $u['username'] . ': ' . $e->getMessage();
            }
        }
        if ($errors !== []) {
            throw new RuntimeException(implode('; ', $errors));
        }
    }

    private function sendOne(string $recipient, string $text): void
    {
        $url = 'http://' . $this->p['server'] . ($this->p['uri'] ?? '/') . 'sendsms?' . http_build_query([
            'sender'    => (string) ($this->p['sender'] ?? 'snmptraps'),
            'recipient' => $recipient,
            'appid'     => (string) ($this->p['appid'] ?? 'snmptraps'),
            'reference' => time(),
            'message'   => $text,
        ]);
        $res = HttpClient::request('GET', $url);
        $json = json_decode($res['body']);
        $status = $json->SendSmsResponse->status ?? null;
        if ($status === null) {
            throw new RuntimeException("invalid response from SMS gateway (HTTP {$res['status']})");
        }
        if ($status !== 'OK') {
            throw new RuntimeException("SMS gateway error [$status]");
        }
    }
}
