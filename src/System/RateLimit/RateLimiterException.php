<?php

declare(strict_types=1);

namespace VEngine\System\RateLimit;

use Throwable;
use VEngine\System\Exception\BaseException;

class RateLimiterException extends BaseException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
