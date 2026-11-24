<?php

declare(strict_types=1);

namespace Snmptraps\Notify;

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
use Snmptraps\Config;
use Snmptraps\Severity;

/**
 * E-mail via PHPMailer (local sendmail or SMTP).
 */
final class MailChannel implements Channel
{
    private array $p;
    private string $title;
    private string $footer;

    public function __construct(Config $config)
    {
        $this->title = $config->title;
        $this->footer = $config->footer;
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

    /** UI colours (Tabler dark theme, gray base) as hex - e-mail clients do not support oklch() */
    private const UI = [
        'body'      => '#111827',
        'card'      => '#1f2937',
        'border'    => '#313a47',
        'text'      => '#e5e7eb',
        'secondary' => '#9ca3af',
        'primary'   => '#4299e1',
        'badge'     => '#233448',   // bg-info-lt on a card
        'font'      => "system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif",
        'mono'      => "SFMono-Regular,Menlo,Consolas,'Liberation Mono',monospace",
    ];

    public function html(Message $m): string
    {
        $t = $m->trap;
        $c = self::UI;
        $sev = Severity::color($t->severity);
        $label = "font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:{$c['secondary']};padding:6px 16px 6px 0;vertical-align:top;white-space:nowrap;";
        $value = "font-size:13px;color:{$c['text']};padding:6px 0;vertical-align:top;";
        $row = static fn (string $l, string $v): string => "<tr><td style=\"$label\">" . e($l) . "</td><td style=\"$value\">$v</td></tr>";

        $badge = "<span style=\"display:inline-block;background:{$c['badge']};color:$sev;font-weight:600;font-size:12px;padding:2px 10px;border-radius:4px;\">" . e($t->severity) . '</span>';
        $content = implode('<br>', array_map('e', $m->contentLines()));

        $buttons = '';
        if ($m->trapUrl() !== '') {
            $btn = "display:inline-block;font-size:13px;font-weight:600;text-decoration:none;padding:8px 14px;border-radius:4px;margin:0 8px 8px 0;";
            $buttons = "<tr><td style=\"padding:8px 24px 16px;\">"
                . "<a href=\"" . e($m->trapUrl()) . "\" style=\"{$btn}background:{$c['primary']};color:#ffffff;border:1px solid {$c['primary']};\">Trap details</a>"
                . "<a href=\"" . e($m->messageUrl()) . "\" style=\"{$btn}background:transparent;color:{$c['text']};border:1px solid {$c['border']};\">All traps with this message</a>"
                . '</td></tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            // page background: transparent (client default) in light mode, UI background in dark mode;
            // the card itself always keeps the UI colours. [data-ogsb] = Outlook.com dark mode.
            . '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark">'
            . "<style>@media (prefers-color-scheme: dark) { body, .snmptraps-bg { background:{$c['body']} !important; } }"
            . "[data-ogsb] body, [data-ogsb] .snmptraps-bg { background:{$c['body']} !important; }</style>"
            . '<title>' . e($m->subject()) . '</title></head>'
            . '<body style="margin:0;padding:0;background:transparent;">'
            . "<table role=\"presentation\" class=\"snmptraps-bg\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:transparent;font-family:{$c['font']};\">"
            . '<tr><td align="center" style="padding:24px 12px;">'
            . "<table role=\"presentation\" width=\"640\" cellpadding=\"0\" cellspacing=\"0\" bgcolor=\"{$c['card']}\" style=\"width:100%;max-width:640px;background:{$c['card']};border:1px solid {$c['border']};border-top:3px solid $sev;border-radius:6px;\">"
            // header
            . '<tr><td style="padding:20px 24px 4px;">'
            . "<div style=\"font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:{$c['secondary']};\">" . e($this->title) . ' &middot; SNMP trap</div>'
            . "<div style=\"font-size:18px;line-height:1.35;font-weight:700;color:$sev;margin-top:6px;\">" . e($t->message) . '</div>'
            . "<div style=\"font-size:13px;color:{$c['secondary']};margin-top:4px;\">" . e($t->hostname) . ' &middot; ' . e($m->date) . '</div>'
            . '</td></tr>'
            // details
            . '<tr><td style="padding:12px 24px 4px;"><table role="presentation" cellpadding="0" cellspacing="0">'
            . $row('Severity', $badge)
            . $row('Host', '<strong>' . e($t->hostname) . '</strong>')
            . $row('IP', e($t->ip))
            . $row('Date', e($m->date))
            . $row('OID', "<span style=\"font-family:{$c['mono']};font-size:12px;\">" . e($t->oid) . '</span>')
            . '</table></td></tr>'
            // content
            . '<tr><td style="padding:8px 24px 16px;">'
            . "<div style=\"$label padding:0 0 6px;\">Content</div>"
            . "<div style=\"background:{$c['body']};border:1px solid {$c['border']};border-radius:4px;padding:10px 12px;font-family:{$c['mono']};font-size:12px;line-height:1.5;color:{$c['text']};word-break:break-all;\">"
            . ($content !== '' ? $content : "<span style=\"color:{$c['secondary']};\">no content</span>") . '</div>'
            . '</td></tr>'
            . $buttons
            . '</table>'
            . "<div style=\"font-size:11px;color:{$c['secondary']};margin-top:12px;\">" . e($this->footer) . ' &middot; php-snmptraps</div>'
            . '</td></tr></table></body></html>';
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
