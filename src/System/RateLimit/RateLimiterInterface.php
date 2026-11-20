<?php

declare(strict_types=1);

namespace VEngine\System\RateLimit;

interface RateLimiterInterface
{
    public function hit(string $bucket, string $identifier, int $decaySeconds): RateLimitResult;

    public function clear(string $bucket, string $identifier): void;
}
