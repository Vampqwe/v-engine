<?php

declare(strict_types=1);

namespace VEngine\System\RateLimit;

final readonly class RateLimitResult
{
    public function __construct(
        public int $attempts,
        public int $retryAfter,
    ) {}
}
