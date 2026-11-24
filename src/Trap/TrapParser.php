<?php

declare(strict_types=1);

namespace Snmptraps\Trap;

use Snmptraps\Severity;

/**
 * Parses the text snmptrapd passes to a traphandle program on STDIN:
 *
 *   line 0  hostname
 *   line 1  transport, e.g. "UDP: [10.0.0.1]:51978->[10.12.50.51]:162"
 *   line 2  "DISMAN-EVENT-MIB::sysUpTimeInstance 264:6:57:25.11"
 *   line 3  "SNMPv2-MIB::snmpTrapOID.0 IF-MIB::linkDown"
 *   line 4+ variable bindings "NAME VALUE"
 *
 * The content/message format produced here is persisted and matched by exception
 * rules and severity definitions, so it intentionally reproduces the historic
 * php-snmptraps output.
 */
final class TrapParser
{
    /** Varbinds removed from content (matched as substring of the content name) */
    private const UNNEEDED = [
        'SNMP-COMMUNITY-MIB::snmpTrap',
        'SNMP-COMMUNITY-MIB::snmpTrapCommunity.0',
        'SNMP-COMMUNITY-MIB::snmpTrapAddress.0 ',
        'SNMPv2-MIB::snmpTrapEnterprise',
        'CISCO-SYSLOG-MIB::clogHistSeverity',
        'CISCO-SYSLOG-MIB::clogHistFacility',
        'CISCO-SYSLOG-MIB::clogHistTimestamp',
    ];

    /** @var list<string> */
    private array $lines;
    /** @var list<string> "name => value" */
    private array $content = [];
    /** @var list<string> full varbind names, parallel to $content (for BGP peer address) */
    private array $fullNames = [];
    private string $module = '';

    /**
     * @param list<array{oid:string,severity:string}> $severityDefinitions ordered by id; the
     *        first definition whose oid is contained in the trap OID wins
     * @param (callable(string):string)|null $resolver reverse DNS used when no hostname is given
     */
    public function __construct(
        private readonly array $severityDefinitions = [],
        private readonly mixed $resolver = null,
    ) {
    }

    /** @param list<string> $lines raw lines including line terminators */
    public function parse(array $lines): Trap
    {
        $this->lines = array_values($lines);
        $this->content = [];
        $this->fullNames = [];

        $ip       = $this->parseIp($this->line(1));
        $hostname = trim($this->line(0));
        if ($hostname === '' && $ip !== '') {
            $hostname = $this->resolver !== null ? ($this->resolver)($ip) : (gethostbyaddr($ip) ?: $ip);
        }
        $uptime = $this->afterSpace($this->line(2));
        $oid    = $this->afterSpace($this->line(3));
        $pos    = strpos($oid, '::');
        $this->module = $pos === false ? '' : substr($oid, 0, $pos);

        $this->parseContent();
        $severity = $this->detectSeverity($oid);
        $message  = $this->detectMessage($oid);
        $this->removeUnneeded();

        return new Trap($hostname, $ip, $uptime, $oid, array_values($this->content), $severity, $message, implode('', $this->lines));
    }

    private function line(int $i): string
    {
        return $this->lines[$i] ?? '';
    }

    private function afterSpace(string $line): string
    {
        $pos = strpos($line, ' ');
        return $pos === false ? '' : trim(substr($line, $pos));
    }

    private function beforeSpace(string $line): string
    {
        $pos = strpos($line, ' ');
        return $pos === false ? '' : trim(substr($line, 0, $pos));
    }

    /**
     * "UDP: [10.0.0.1]:51978->[10.12.50.51]:162" or "UDP/IPv6: [2001:db8::1]:..." -> source address
     */
    private function parseIp(string $line): string
    {
        if (preg_match('/\[([0-9A-Fa-f:.]+)\]/', $line, $m)) {
            return $m[1];
        }
        $tmp = str_replace(['UDP:', '[', ']'], '', $line);
        $pos = strpos($tmp, ':');
        return trim($pos === false ? $tmp : substr($tmp, 0, $pos));
    }

    private function parseContent(): void
    {
        $count = count($this->lines);
        if ($count <= 4) {
            $this->content = ['NONE'];
            $this->fullNames = [''];
            return;
        }
        // first pass: only varbinds from the trap's own MIB module
        for ($m = 4; $m < $count; $m++) {
            if (str_contains($this->lines[$m], $this->module)) {
                $this->addVarbind($this->lines[$m]);
            }
        }
        // fallback: everything
        if ($this->content === []) {
            for ($m = 4; $m < $count; $m++) {
                $this->addVarbind($this->lines[$m]);
            }
        }
    }

    private function addVarbind(string $line): void
    {
        $value = $this->afterSpace($line);
        $full  = $this->beforeSpace($line);
        // drop the instance index (last dot component) and the trap's own module prefix
        $parts = explode('.', $full);
        array_pop($parts);
        $name = str_replace($this->module . '::', '', implode('.', $parts));

        $this->content[]   = $name . ' => ' . $value;
        $this->fullNames[] = $full;
    }

    /**
     * Split a content line. Intentionally unlimited explode (value = 2nd element only)
     * to reproduce historic message text for values that themselves contain " => ".
     *
     * @return array{0:string,1:string}
     */
    private static function split(string $c): array
    {
        $p = explode(' => ', $c);
        return [$p[0], $p[1] ?? ''];
    }

    private function detectSeverity(string $oid): string
    {
        $severity = Severity::UNKNOWN;
        foreach ($this->content as $c) {
            if (str_contains($c, 'Severity') || str_contains($c, 'severity')) {
                $severity = Severity::normalize(self::split($c)[1]);
            }
        }
        foreach ($this->severityDefinitions as $d) {
            if ($d['oid'] !== '' && str_contains($oid, $d['oid'])) {
                $severity = Severity::normalize((string) $d['severity']);
                break;
            }
        }
        return $severity;
    }

    private function detectMessage(string $oid): string
    {
        $pos = strpos($oid, '::');
        $msg = $pos === false ? '' : str_replace('::', '', substr($oid, $pos));

        $changed = false;
        foreach (['Msg', 'msg', 'Message', 'message'] as $sv) {
            foreach ($this->content as $c) {
                if (str_contains($c, $sv)) {
                    $value = self::split($c)[1];
                    $msg = $changed ? $msg . ' :: ' . $value : $value;
                    $changed = true;
                }
            }
        }

        return $this->detectSpecial($msg);
    }

    /**
     * Vendor/protocol specific message enrichment.
     */
    private function detectSpecial(string $msg): string
    {
        $original = $msg;

        // interface state
        if (in_array($original, ['linkUp', 'linkDown', 'cieLinkUp', 'cieLinkDown', 'ipv6IfStateChange'], true)) {
            foreach ($this->content as $c) {
                [$name, $value] = self::split($c);
                if ($name === 'ifName') {
                    $msg = match ($original) {
                        'linkUp', 'cieLinkUp'     => "Interface $value changed state to Up",
                        'linkDown', 'cieLinkDown' => "Interface $value changed state to Down",
                        default                   => "Interface $value IPv6 state change",
                    };
                } elseif ($name === 'ifDescr') {
                    $msg .= " ($value)";
                } elseif ($name === 'ifAlias') {
                    $msg .= " $value";
                }
            }
        }

        // STP
        if (in_array($original, ['topologyChange', 'newRoot'], true)) {
            foreach ($this->lines as $line) {
                $c = explode(' ', trim($line));
                if (str_contains($c[0], 'ifName')) {
                    $msg .= ' (Interface ' . ($c[1] ?? '') . ')';
                } elseif (str_contains($c[0], 'vtpVlanIndex')) {
                    $msg .= ' :: vlan ' . ($c[1] ?? '');
                }
            }
        }

        // VTP
        if ($original === 'vtpVlanCreated') {
            foreach ($this->content as $i => $c) {
                [$name, $value] = self::split($c);
                if (str_contains($name, 'vtpVlanName')) {
                    // full name is vtpVlanName.<domain>.<vlan>
                    $tmp = explode('.', $this->fullNames[$i] ?? $name);
                    $msg .= " :: $value (vlan " . array_pop($tmp) . ')';
                }
            }
        }

        // IKE
        if (in_array($original, ['cikeTunnelStart', 'cikeTunnelStop'], true)) {
            foreach ($this->content as $c) {
                [$name, $value] = self::split($c);
                if (str_contains($name, 'cikePeerRemoteAddr')) {
                    $ip = self::hexToIp($value);
                    $msg .= " :: peer $ip";
                    $this->content[] = "Remote address => $ip";
                    $this->fullNames[] = '';
                } elseif (str_contains($name, 'cikeTunHistTermReason')) {
                    $msg .= " ($value)";
                    $this->content[] = "Terminate reason => $value";
                    $this->fullNames[] = '';
                }
            }
        }

        // DISMAN event trigger
        if ($original === 'mteTriggerFired') {
            foreach ($this->content as $c) {
                [$name, $value] = self::split($c);
                if ($name === 'mteHotTrigger') {
                    $msg .= " :: $value";
                } elseif ($name === 'mteHotValue') {
                    $msg .= " ($value%)";
                }
            }
        }

        // SNMP authentication failure
        if ($original === 'authenticationFailure') {
            foreach ($this->lines as $line) {
                $c = explode(' ', trim($line));
                if (str_contains($c[0], 'authAddr')) {
                    $msg .= ' :: ' . ($c[1] ?? '');
                }
            }
        }

        // BGP
        if (in_array($original, ['bgpEstablished', 'bgpBackwardTransition'], true)) {
            foreach ($this->content as $i => $c) {
                [$name, $value] = self::split($c);
                if (str_contains($name, 'bgpPeerState')) {
                    $full = $this->fullNames[$i] ?? '';
                    $peer = ($p = strpos($full, 'bgpPeerState.')) !== false ? substr($full, $p + 13) : str_replace('bgpPeerState.', '', $name);
                    $msg .= " :: peer $peer (state $value)";
                }
            }
        }

        return $msg;
    }

    private function removeUnneeded(): void
    {
        foreach (self::UNNEEDED as $uv) {
            foreach ($this->content as $k => $c) {
                if (str_contains(self::split($c)[0], $uv)) {
                    unset($this->content[$k], $this->fullNames[$k]);
                }
            }
        }
    }

    /** "0A 00 00 01" -> "10.0.0.1" */
    private static function hexToIp(string $hex): string
    {
        $parts = array_filter(explode(' ', trim(str_replace('"', '', $hex))), static fn ($p) => $p !== '');
        return implode('.', array_map(static fn ($p) => (string) hexdec($p), $parts));
    }
}
