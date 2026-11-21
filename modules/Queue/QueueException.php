<?php

declare(strict_types=1);

namespace Modules\Queue;

use RuntimeException;
use Throwable;

final class QueueException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
