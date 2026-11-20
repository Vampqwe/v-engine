<?php

declare(strict_types=1);

namespace VEngine\System\RateLimit;

use Throwable;
use VEngine\System\Redis\RedisClient;

final class RedisRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly RedisClient $redis,
    ) {}

    public function hit(string $bucket, string $identifier, int $decaySeconds): RateLimitResult
    {
        try {
            $client = $this->redis->connection();
            $key = $this->buildKey($bucket, $identifier);
            $attempts = $client->incr($key);

            if ($attempts === false) {
                throw new RateLimiterException('Redis не смог увеличить счетчик запросов');
            }

            if ($attempts === 1 && !$client->expire($key, $decaySeconds)) {
                throw new RateLimiterException('Redis не смог установить срок жизни счетчика');
            }

            $ttl = $client->ttl($key);
            if ($ttl === false) {
                throw new RateLimiterException('Redis не смог получить срок жизни счетчика');
            }

            $retryAfter = (int) $ttl;
            if ($retryAfter < 0) {
                if (!$client->expire($key, $decaySeconds)) {
                    throw new RateLimiterException('Redis не смог восстановить срок жизни счетчика');
                }
                $retryAfter = $decaySeconds;
            }

            return new RateLimitResult($attempts, $retryAfter);
        } catch (Throwable $e) {
            if ($e instanceof RateLimiterException) {
                throw $e;
            }
            throw new RateLimiterException('Ошибка Redis при проверке лимита запросов', $e);
        }
    }

    public function clear(string $bucket, string $identifier): void
    {
        try {
            if ($this->redis->connection()->del($this->buildKey($bucket, $identifier)) === false) {
                throw new RateLimiterException('Redis не смог удалить счетчик запросов');
            }
        } catch (Throwable $e) {
            if ($e instanceof RateLimiterException) {
                throw $e;
            }
            throw new RateLimiterException('Ошибка Redis при сбросе лимита запросов', $e);
        }
    }

    private function buildKey(string $bucket, string $identifier): string
    {
        return sprintf('ratelimit:%s:%s', $bucket, sha1($identifier));
    }
}
