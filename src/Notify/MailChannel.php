<?php

declare(strict_types=1);

namespace Snmptraps\Notify;

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
use Snmptraps\Config;

/**
 * E-mail via PHPMailer (local sendmail or SMTP).
 */
final class MailChannel implements Channel
{
    private array $p;

    public function __construct(Config $config)
    {
        $p = $config->notificationParams['mail'] ?? null;
        if (!is_array($p) || empty($p['from'])) {
            throw new RuntimeException('Invalid mail parameters');
        }
        $this->p = $p;
    }

    public function send(Message $message, array $users): void
    {
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            if (($this->p['type'] ?? 'localhost') === 'smtp') {
                $this->configureSmtp($mail);
            }
            $mail->setFrom((string) $this->p['from'], (string) ($this->p['from_name'] ?? 'php-snmptraps'));

            $added = 0;
            foreach ($users as $u) {
                $email = trim((string) ($u['email'] ?? ''));
                if ($email !== '' && PHPMailer::validateAddress($email)) {
                    $mail->addAddress($email, trim((string) ($u['real_name'] ?? '')));
                    $added++;
                }
            }
            if ($added === 0) {
                return;
            }

            $mail->Subject = $message->subject();
            $mail->isHTML(true);
            $mail->Body    = $this->html($message);
            $mail->AltBody = $this->plain($message);
            $mail->send();
        } catch (MailerException $e) {
            throw new RuntimeException('Mailer error: ' . $e->getMessage(), 0, $e);
        }
    }

    private function configureSmtp(PHPMailer $mail): void
    {
        $mail->isSMTP();
        $mail->Host = (string) ($this->p['server'] ?? '127.0.0.1');
        $mail->Port = (int) ($this->p['port'] ?? 25);
        $security = (string) ($this->p['security'] ?? 'none');
        if ($security === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($security === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        if (empty($this->p['verify_peer'])) {
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        }
        $auth = $this->p['auth'] ?? false;
        if ($auth === true || $auth === 'yes') {
            $mail->SMTPAuth = true;
            $mail->Username = (string) ($this->p['user'] ?? '');
            $mail->Password = (string) ($this->p['pass'] ?? '');
        }
    }

    private function html(Message $m): string
    {
        $t = $m->trap;
        $row = static fn (string $label, string $value, bool $strong = false) =>
            "<tr><td style='vertical-align:top;'>" . e($label) . ":</td><td style='padding-left:10px;vertical-align:top;'>"
            . ($strong ? '<strong>' . $value . '</strong>' : $value) . '</td></tr>';

        $links = [];
        if ($m->trapUrl() !== '') {
            $links[] = "<a href='" . e($m->trapUrl()) . "' style='color:#08c;'>Trap details</a>";
            $links[] = "<a href='" . e($m->messageUrl()) . "' style='color:#08c;'>All traps with this message</a>";
        }

        return "<!DOCTYPE html><html><head><meta charset='utf-8'></head>"
            . "<body style='margin:0;padding:0;background:#f9f9f9;'>"
            . "<div style='padding:10px;font-family:Helvetica,Verdana,Arial,sans-serif;font-size:12px;color:#333;'>"
            . 'New snmp trap received:<br><br><table>'
            . $row('Hostname', e($t->hostname), true)
            . $row('IP', e($t->ip))
            . $row('Message', e($t->message), true)
            . $row('Date', e($m->date))
            . $row('Severity', e($t->severity), true)
            . $row('OID', e($t->oid))
            . $row('Content', implode('<br>', array_map('e', $m->contentLines())))
            . '</table>'
            . ($links ? '<br>' . implode(' &middot; ', $links) : '')
            . '</div></body></html>';
    }

    private function plain(Message $m): string
    {
        $t = $m->trap;
        return "New snmp trap received:\n\n"
            . "Hostname: {$t->hostname}\nIP: {$t->ip}\nMessage: {$t->message}\nDate: {$m->date}\n"
            . "Severity: {$t->severity}\nOID: {$t->oid}\nContent:\n  " . implode("\n  ", $m->contentLines())
            . ($m->trapUrl() !== '' ? "\n\n" . $m->trapUrl() : '');
    }
}
