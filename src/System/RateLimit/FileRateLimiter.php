<?php

declare(strict_types=1);

namespace VEngine\System\RateLimit;

use JsonException;
use Throwable;
use VEngine\System\TimeDate\TimeDateInterface;

final class FileRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly string $storageDir,
        private readonly TimeDateInterface $timeDate,
    ) {}

    public function hit(string $bucket, string $identifier, int $decaySeconds): RateLimitResult
    {
        if ($decaySeconds < 1) {
            throw new RateLimiterException('Период ограничения запросов должен быть положительным');
        }

        return $this->withState($bucket, $identifier, function (array &$state) use ($decaySeconds): RateLimitResult {
            $now = $this->timeDate->timestamp();

            if ($state['window_started_at'] === 0 || $now >= $state['window_started_at'] + $decaySeconds) {
                $state['attempts'] = 0;
                $state['window_started_at'] = $now;
            }

            $state['attempts']++;
            $state['fallback_until'] = max($state['fallback_until'], $now + $decaySeconds);

            return new RateLimitResult(
                $state['attempts'],
                max(0, $state['window_started_at'] + $decaySeconds - $now),
            );
        });
    }

    public function clear(string $bucket, string $identifier): void
    {
        $this->withState($bucket, $identifier, function (array &$state): void {
            $state['attempts'] = 0;
            $state['window_started_at'] = $this->timeDate->timestamp();
        });
    }

    public function isFailoverActive(string $bucket, string $identifier): bool
    {
        return $this->withState(
            $bucket,
            $identifier,
            fn(array &$state): bool => $state['fallback_until'] > $this->timeDate->timestamp(),
            false,
        );
    }

    public function activateFailover(string $bucket, string $identifier, int $holdSeconds): void
    {
        if ($holdSeconds < 1) {
            throw new RateLimiterException('Период файлового failover должен быть положительным');
        }

        $this->withState($bucket, $identifier, function (array &$state) use ($holdSeconds): void {
            $state['fallback_until'] = max(
                $state['fallback_until'],
                $this->timeDate->timestamp() + $holdSeconds,
            );
        });
    }

    private function withState(string $bucket, string $identifier, callable $callback, bool $persist = true): mixed
    {
        $path = $this->storageDir . '/' . hash('sha256', $bucket . "\0" . $identifier) . '.json';
        $handle = null;
        $locked = false;

        try {
            if (!is_dir($this->storageDir)
                && !@mkdir($this->storageDir, 0o700, true)
                && !is_dir($this->storageDir)
            ) {
                throw new RateLimiterException('Не удалось создать каталог файлового rate limiter');
            }

            $handle = fopen($path, 'c+');
            if ($handle === false) {
                throw new RateLimiterException('Не удалось открыть состояние файлового rate limiter');
            }
            chmod($path, 0o600);

            if (!flock($handle, LOCK_EX)) {
                throw new RateLimiterException('Не удалось заблокировать состояние файлового rate limiter');
            }
            $locked = true;

            rewind($handle);
            $contents = stream_get_contents($handle);
            if ($contents === false) {
                throw new RateLimiterException('Не удалось прочитать состояние файлового rate limiter');
            }
            $state = $this->decodeState($contents);
            $result = $callback($state);

            if ($persist) {
                $this->writeState($handle, $state);
            }

            return $result;
        } catch (RateLimiterException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RateLimiterException('Ошибка файлового rate limiter', $e);
        } finally {
            if (is_resource($handle)) {
                if ($locked) {
                    flock($handle, LOCK_UN);
                }
                fclose($handle);
            }
        }
    }

    private function decodeState(string $contents): array
    {
        if ($contents === '') {
            return [
                'attempts'          => 0,
                'window_started_at' => 0,
                'fallback_until'    => 0,
            ];
        }

        try {
            $state = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RateLimiterException('Повреждено состояние файлового rate limiter', $e);
        }

        if (
            !is_array($state)
            || !is_int($state['attempts'] ?? null)
            || $state['attempts'] < 0
            || !is_int($state['window_started_at'] ?? null)
            || $state['window_started_at'] < 0
            || !is_int($state['fallback_until'] ?? null)
            || $state['fallback_until'] < 0
        ) {
            throw new RateLimiterException('Некорректное состояние файлового rate limiter');
        }

        return $state;
    }

    private function writeState(mixed $handle, array $state): void
    {
        try {
            $contents = json_encode($state, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RateLimiterException('Не удалось сериализовать состояние файлового rate limiter', $e);
        }

        rewind($handle);
        if (!ftruncate($handle, 0)) {
            throw new RateLimiterException('Не удалось очистить состояние файлового rate limiter');
        }

        $written = 0;
        $length = strlen($contents);
        while ($written < $length) {
            $bytes = fwrite($handle, substr($contents, $written));
            if ($bytes === false || $bytes === 0) {
                throw new RateLimiterException('Не удалось записать состояние файлового rate limiter');
            }
            $written += $bytes;
        }

        if (!fflush($handle)) {
            throw new RateLimiterException('Не удалось сохранить состояние файлового rate limiter');
        }
    }
}
