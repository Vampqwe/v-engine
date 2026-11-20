<?php

declare(strict_types=1);

namespace VEngine\System\RateLimit;

use Throwable;
use VEngine\System\Monitoring\RequestMetrics;
use VEngine\System\Redis\RedisClient;

final class RedisRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly RedisClient $redis,
        private readonly RequestMetrics $metrics,
    ) {}

    public function hit(string $bucket, string $identifier, int $decaySeconds): RateLimitResult
    {
        $startedAt = hrtime(true);
        try {
            $client = $this->redis->connection();
            $key = $this->buildKey($bucket, $identifier);
            $script = <<<'LUA'
local attempts = redis.call('INCR', KEYS[1])
if attempts == 1 then
    redis.call('EXPIRE', KEYS[1], ARGV[1])
end
local ttl = redis.call('TTL', KEYS[1])
if ttl < 0 then
    redis.call('EXPIRE', KEYS[1], ARGV[1])
    ttl = tonumber(ARGV[1])
end
return {attempts, ttl}
LUA;
            $result = $client->eval($script, [$key, $decaySeconds], 1);
            if (!is_array($result) || !isset($result[0], $result[1]) || !is_numeric($result[0]) || !is_numeric($result[1])) {
                throw new RateLimiterException('Redis вернул некорректный результат проверки лимита');
            }

            return new RateLimitResult((int) $result[0], (int) $result[1]);
        } catch (Throwable $e) {
            $this->redis->resetConnection();
            if ($e instanceof RateLimiterException) {
                throw $e;
            }
            throw new RateLimiterException('Ошибка Redis при проверке лимита запросов', $e);
        } finally {
            $this->metrics->record('redis', hrtime(true) - $startedAt);
        }
    }

    public function clear(string $bucket, string $identifier): void
    {
        $startedAt = hrtime(true);
        try {
            if ($this->redis->connection()->del($this->buildKey($bucket, $identifier)) === false) {
                throw new RateLimiterException('Redis не смог удалить счетчик запросов');
            }
        } catch (Throwable $e) {
            $this->redis->resetConnection();
            if ($e instanceof RateLimiterException) {
                throw $e;
            }
            throw new RateLimiterException('Ошибка Redis при сбросе лимита запросов', $e);
        } finally {
            $this->metrics->record('redis', hrtime(true) - $startedAt);
        }
    }

    private function buildKey(string $bucket, string $identifier): string
    {
        return sprintf('ratelimit:%s:%s', $bucket, sha1($identifier));
    }
}
