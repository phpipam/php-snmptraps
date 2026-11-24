<?php

declare(strict_types=1);

namespace Snmptraps\Web;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message, $status);
    }
}
