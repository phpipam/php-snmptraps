<?php

declare(strict_types=1);

namespace Snmptraps\Notify;

use Snmptraps\Trap\Trap;

/**
 * Notification payload shared by all channels.
 */
final class Message
{
    public readonly string $date;

    public function __construct(
        public readonly Trap $trap,
        public readonly int $trapId,
        private readonly string $baseUrl,
    ) {
        $this->date = date('d.m.Y H:i:s');   // same format as the UI
    }

    public function subject(): string
    {
        return '[' . $this->trap->hostname . '] - ' . $this->trap->message;
    }

    public function trapUrl(): string
    {
        return $this->baseUrl === '' ? '' : $this->baseUrl . 'trap/' . $this->trapId . '/';
    }

    public function messageUrl(): string
    {
        return $this->baseUrl === '' ? '' : $this->baseUrl . 'message/' . b64url_encode($this->trap->message) . '/';
    }

    /** @return list<string> */
    public function contentLines(): array
    {
        return $this->trap->content;
    }
}
