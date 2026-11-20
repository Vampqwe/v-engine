<?php

declare(strict_types=1);

namespace VEngine\System\Cache;

use Throwable;
use VEngine\System\Monitoring\RequestMetrics;
use VEngine\System\Redis\RedisClient;
use VEngine\System\TimeDate\TimeDateInterface;

final class RedisCache extends AbstractCache
{
    public function __construct(
        private readonly RedisClient $redis,
        private readonly RequestMetrics $metrics,
        TimeDateInterface $timeDate,
    ) {
        parent::__construct($timeDate);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $startedAt = hrtime(true);
        try {
            $raw = $this->redis->connection()->get($this->formatKey($key));
            if ($raw === false || !is_string($raw)) {
                return $default;
            }

            $payload = @unserialize($raw, ['allowed_classes' => false]);
            if (
                !is_array($payload)
                || !array_key_exists('data', $payload)
                || $this->isExpired($payload)
            ) {
                $this->delete($key);
                return $default;
            }

            return $payload['data'];
        } catch (Throwable $e) {
            $this->redis->resetConnection();
            throw new CacheException('Ошибка чтения из RedisCache: ' . $e->getMessage(), 0, $e);
        } finally {
            $this->metrics->record('redis', hrtime(true) - $startedAt);
        }
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        if ($ttl <= 0) {
            return $this->delete($key);
        }

        $startedAt = hrtime(true);
        try {
            $payload = serialize($this->preparePayload($value, $ttl));
            return (bool) $this->redis->connection()->setex($this->formatKey($key), $ttl, $payload);
        } catch (Throwable $e) {
            $this->redis->resetConnection();
            throw new CacheException('Ошибка записи в RedisCache: ' . $e->getMessage(), 0, $e);
        } finally {
            $this->metrics->record('redis', hrtime(true) - $startedAt);
        }
    }

    public function delete(string $key): bool
    {
        $startedAt = hrtime(true);
        try {
            $this->redis->connection()->del($this->formatKey($key));
            return true;
        } catch (Throwable $e) {
            $this->redis->resetConnection();
            throw new CacheException('Ошибка удаления ключа в RedisCache: ' . $e->getMessage(), 0, $e);
        } finally {
            $this->metrics->record('redis', hrtime(true) - $startedAt);
        }
    }

    private function formatKey(string $key): string
    {
        return 'cache:' . $key;
    }
}
