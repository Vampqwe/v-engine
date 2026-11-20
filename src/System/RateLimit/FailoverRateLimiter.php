<?php

declare(strict_types=1);

namespace VEngine\System\RateLimit;

use VEngine\System\Logger\LoggerInterface;

final class FailoverRateLimiter implements RateLimiterInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly RateLimiterInterface $primary,
        private readonly FileRateLimiter $fallback,
        private readonly int $fallbackHoldSeconds,
        LoggerInterface $logger,
    ) {
        if ($fallbackHoldSeconds < 1) {
            throw new \InvalidArgumentException('Период файлового failover должен быть положительным');
        }

        $this->logger = $logger->forModule('Security');
    }

    public function hit(string $bucket, string $identifier, int $decaySeconds): RateLimitResult
    {
        if ($this->fallback->isFailoverActive($bucket, $identifier)) {
            return $this->fallback->hit($bucket, $identifier, $decaySeconds);
        }

        try {
            $result = $this->primary->hit($bucket, $identifier, $decaySeconds);
        } catch (RateLimiterException $e) {
            $this->activateFallback($bucket, $identifier, $decaySeconds, $e);
            return $this->fallback->hit($bucket, $identifier, $decaySeconds);
        }

        if ($this->fallback->isFailoverActive($bucket, $identifier)) {
            return $this->fallback->hit($bucket, $identifier, $decaySeconds);
        }

        return $result;
    }

    public function clear(string $bucket, string $identifier): void
    {
        if ($this->fallback->isFailoverActive($bucket, $identifier)) {
            $this->fallback->clear($bucket, $identifier);
            return;
        }

        try {
            $this->primary->clear($bucket, $identifier);
        } catch (RateLimiterException $e) {
            $this->activateFallback($bucket, $identifier, $this->fallbackHoldSeconds, $e);
            $this->fallback->clear($bucket, $identifier);
        }
    }

    private function activateFallback(
        string $bucket,
        string $identifier,
        int $holdSeconds,
        RateLimiterException $cause,
    ): void {
        $this->logger->error('Redis rate limiter недоступен; включён файловый fallback', [
            'bucket'    => $bucket,
            'exception' => $cause::class,
            'message'   => $cause->getMessage(),
        ]);
        $this->fallback->activateFailover($bucket, $identifier, $holdSeconds);
    }
}
