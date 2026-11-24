<?php

declare(strict_types=1);

namespace Snmptraps\Trap;

/**
 * A parsed SNMP trap.
 */
final class Trap
{
    /**
     * @param list<string> $content variable bindings, each formatted "name => value"
     *                              (this exact format is stored in traps.content and
     *                              matched by exceptions - do not change it)
     */
    public function __construct(
        public string $hostname,
        public string $ip,
        public string $uptime,
        public string $oid,
        public array $content,
        public string $severity,
        public string $message,
        public string $raw,
    ) {
    }

    public function contentText(): string
    {
        return implode("\n", $this->content);
    }

    public function toArray(): array
    {
        return [
            'hostname' => $this->hostname,
            'ip'       => $this->ip,
            'uptime'   => $this->uptime,
            'oid'      => $this->oid,
            'severity' => $this->severity,
            'message'  => $this->message,
            'content'  => $this->contentText(),
        ];
    }
}
