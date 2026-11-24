<?php

declare(strict_types=1);

namespace Snmptraps\Web;

/**
 * Screenshot mode: replaces hostnames and IP / MAC addresses in rendered HTML with a mask.
 *
 * Only text nodes and the tooltip / title attributes are changed - href, value etc. stay
 * real, so links and forms keep working. Enabled per browser by the cookie
 * snmptraps-anonymize=1 (user menu -> "Screenshot mode"); nothing is stored differently.
 */
final class Anonymizer
{
    public const COOKIE = 'snmptraps-anonymize';
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

    /** @var array<string,string> hostname => mask, longest first */
    private array $hosts = [];

    /** @param list<string> $hostnames */
    public function __construct(array $hostnames)
    {
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

    public static function enabled(): bool
    {
        return ($_COOKIE[self::COOKIE] ?? '') === '1';
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
        $s = strtr($s, $this->hosts);
        return preg_replace(self::PATTERNS, self::MASK, $s) ?? $s;
    }
}
