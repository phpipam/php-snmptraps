<?php

declare(strict_types=1);

namespace Snmptraps\Web;

/**
 * Screenshot mode: replaces hostnames, IP / MAC addresses and users' personal data (names,
 * usernames, e-mail addresses, phone numbers) in rendered HTML with a mask.
 *
 * Only text nodes and the tooltip / title attributes are changed - href, value etc. stay
 * real, so links and forms keep working. Enabled for all users by $screenshot_mode = true
 * in config.php (deliberately not in config.dist.php); nothing is stored differently.
 */
final class Anonymizer
{
    public const MASK = '******';

    /** IPv4 (also "ipv4.a.b.c.d" OID indexes), IPv6 with "::" or 6+ groups, MAC addresses */
    private const PATTERNS = [
        // OID index after "ipv4." holds one or more addresses (e.g. local + remote BGP peer)
        '/(?<=ipv4\.)\d{1,3}(?:\.\d{1,3}){3,}/i',
        // plain IPv4, but not a piece of a longer numeric OID (1.3.6.1.4.1...)
        '/(?<![\d.])(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)(?!\d|\.\d)/',
        '/(?<![\w:])(?:[0-9a-f]{1,4}:){5,7}[0-9a-f]{1,4}(?![\w:])/i',
        // compressed IPv6 ("fe80::1", "::1", "2001:db8::") - needs a hex group next to "::",
        // so " :: " in syslog messages and "MIB::name" are not matched
        '/(?<![\w:])(?:[0-9a-f]{1,4}(?::[0-9a-f]{1,4})*::(?:[0-9a-f]{1,4}(?::[0-9a-f]{1,4})*)?|::[0-9a-f]{1,4}(?::[0-9a-f]{1,4})*)(?![\w:])/i',
    ];

    /** attributes whose (HTML) value is shown to the user */
    private const ATTRIBUTES = '/(\s(?:tippy-title|title|placeholder)=)(["\'])(.*?)\2/s';

    /** e-mail addresses */
    private const EMAIL = '/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/u';

    /** @var array<string,string> hostname / literal => mask (strtr prefers the longest match) */
    private array $hosts = [];
    /** regex for people (real names, usernames) - whole words only, so "Admin" keeps "Administrator" */
    private ?string $people = null;

    /**
     * @param list<string> $hostnames
     * @param list<string> $names    users' real names and usernames (masked as whole words)
     * @param list<string> $literals other values to mask anywhere, e.g. phone numbers
     */
    public function __construct(array $hostnames, array $names = [], array $literals = [])
    {
        foreach ($literals as $l) {
            if (mb_strlen(trim((string) $l)) >= 4) {
                $this->hosts[trim((string) $l)] = self::MASK;
            }
        }
        $words = [];
        foreach ($names as $n) {
            $n = trim((string) $n);
            if (mb_strlen($n) >= 3 && strcasecmp($n, 'admin') !== 0) {
                $words[] = preg_quote(htmlspecialchars($n, ENT_QUOTES, 'UTF-8'), '/');
            }
        }
        if ($words !== []) {
            usort($words, static fn ($a, $b) => strlen($b) <=> strlen($a));
            $this->people = '/(?<![\w.@-])(?:' . implode('|', $words) . ')(?![\w@-]|\.\w)/u';
        }
        foreach ($hostnames as $h) {
            $h = trim($h);
            // short names (before the first dot) are shown when strip_hostname_domain is on
            foreach ([$h, explode('.', $h)[0]] as $name) {
                if (mb_strlen($name) >= 4 && !filter_var($name, FILTER_VALIDATE_IP)) {
                    $this->hosts[$name] = self::MASK;
                }
            }
        }
    }

    /** mask a rendered HTML page or fragment */
    public function apply(string $html): string
    {
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }
        $inScript = false;
        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<') {
                if (preg_match('/^<(\/?)(script|style)\b/i', $part, $m)) {
                    $inScript = $m[1] === '';
                }
                $parts[$i] = preg_replace_callback(
                    self::ATTRIBUTES,
                    fn (array $a): string => $a[1] . $a[2] . $this->text($a[3]) . $a[2],
                    $part,
                ) ?? $part;
            } elseif (!$inScript) {
                $parts[$i] = $this->text($part);
            }
        }
        return implode('', $parts);
    }

    private function text(string $s): string
    {
        $s = preg_replace(self::EMAIL, self::MASK, $s) ?? $s;
        $s = strtr($s, $this->hosts);
        if ($this->people !== null) {
            $s = preg_replace($this->people, self::MASK, $s) ?? $s;
        }
        return preg_replace(self::PATTERNS, self::MASK, $s) ?? $s;
    }
}
