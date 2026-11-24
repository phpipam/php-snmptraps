<?php

declare(strict_types=1);

namespace Snmptraps;

/**
 * Append-only file logger for the trap receiver (config: $filename).
 * Falls back to PHP's error_log when no file is configured or it is not writable.
 */
final class Logger
{
    public function __construct(private readonly string|false $file)
    {
    }

    public function error(string $msg): void
    {
        $this->write('ERROR ' . $msg);
    }

    public function info(string $msg): void
    {
        $this->write('INFO ' . $msg);
    }

    public function raw(string $text): void
    {
        if ($this->file !== false) {
            @file_put_contents($this->file, $text . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    private function write(string $msg): void
    {
        $line = date('Y-m-d H:i:s') . ' :: ' . str_replace(["\r", "\n"], ' ', $msg);
        if ($this->file === false || @file_put_contents($this->file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            error_log('snmptraps: ' . $line);
        }
    }
}
