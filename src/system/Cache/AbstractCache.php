<?php

declare(strict_types=1);

namespace VEngine\System\Cache;

use VEngine\System\TimeDate\TimeDateInterface;

abstract class AbstractCache implements CacheInterface
{
    public function __construct(
        protected readonly TimeDateInterface $timeDate
    ) {}
    protected function preparePayload(mixed $value, int $ttl): array
    {
        return [
            'expires_at' => $this->timeDate->timestamp() + $ttl,
            'data' => $value,
        ];
    }
    protected function isExpired(array $payload): bool
    {
        return !isset($payload['expires_at']) || $this->timeDate->timestamp() > $payload['expires_at'];
    }
}
