<?php

declare(strict_types=1);

namespace VEngine\System\RateLimit;

use JsonException;
use FilesystemIterator;
use Throwable;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\TimeDate\TimeDateInterface;

final class FileRateLimiter implements RateLimiterInterface
{
    private const GARBAGE_COLLECTION_SCAN_LIMIT = 200;

    private ?LoggerInterface $logger;

    public function __construct(
        private readonly string $storageDir,
        private readonly TimeDateInterface $timeDate,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger?->forModule('Security');
    }

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

    private function withState(
        string $bucket,
        string $identifier,
        callable $callback,
        bool $persist = true,
        bool $create = true,
    ): mixed {
        $path = $this->getStatePath($bucket, $identifier);
        if (!$create && !is_file($path)) {
            $state = $this->emptyState();
            return $callback($state);
        }

        $this->ensureDirectory($this->storageDir);
        try {
            $this->collectGarbageOccasionally();
        } catch (Throwable $e) {
            $this->logGarbageCollectionFailure($e);
        }

        $gcLock = @fopen($this->storageDir . '/.gc.lock', 'c');
        if ($gcLock === false || !flock($gcLock, LOCK_SH)) {
            if (is_resource($gcLock)) {
                fclose($gcLock);
            }
            throw new RateLimiterException('Не удалось заблокировать каталог файлового rate limiter');
        }

        $handle = null;
        $locked = false;

        try {
            $directory = dirname($path);
            $this->ensureDirectory($directory);

            if (!$create && !is_file($path)) {
                $state = $this->emptyState();
                return $callback($state);
            }

            $isNew = !is_file($path);
            $handle = fopen($path, 'c+');
            if ($handle === false) {
                throw new RateLimiterException('Не удалось открыть состояние файлового rate limiter');
            }
            if ($isNew && !chmod($path, 0o600)) {
                throw new RateLimiterException('Не удалось защитить состояние файлового rate limiter');
            }

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
            flock($gcLock, LOCK_UN);
            fclose($gcLock);
        }
    }

    private function collectGarbageOccasionally(): void
    {
        if (mt_rand(1, 1000) !== 1) {
            return;
        }

        $this->collectExpiredStatesWithLock(false);
    }

    public function collectExpiredStates(bool $waitForLock = true): int
    {
        return $this->collectExpiredStatesWithLock($waitForLock);
    }

    private function collectExpiredStatesWithLock(bool $waitForLock): int
    {
        $this->ensureDirectory($this->storageDir);
        $lock = @fopen($this->storageDir . '/.gc.lock', 'c');
        if ($lock === false) {
            throw new RateLimiterException('Не удалось открыть блокировку очистки rate limiter');
        }
        $operation = LOCK_EX | ($waitForLock ? 0 : LOCK_NB);
        if (!@flock($lock, $operation)) {
            fclose($lock);
            return 0;
        }

        try {
            $cutoff = $this->timeDate->timestamp() - 86400;
            $removed = 0;
            $shards = [];
            foreach (new FilesystemIterator($this->storageDir, FilesystemIterator::SKIP_DOTS) as $entry) {
                if ($entry->isDir() && preg_match('/\A[a-f0-9]{2}\z/', $entry->getFilename()) === 1) {
                    $shards[] = $entry->getPathname();
                }
            }
            shuffle($shards);

            $scanned = 0;
            foreach ($shards as $shard) {
                foreach (new FilesystemIterator($shard, FilesystemIterator::SKIP_DOTS) as $entry) {
                    if ($scanned >= self::GARBAGE_COLLECTION_SCAN_LIMIT) {
                        break 2;
                    }
                    if (!$entry->isFile() || $entry->getExtension() !== 'json') {
                        continue;
                    }
                    $scanned++;

                    if ($entry->getMTime() >= $cutoff) {
                        continue;
                    }

                    $path = $entry->getPathname();
                    $contents = @file_get_contents($path);
                    if ($contents === false) {
                        continue;
                    }

                    try {
                        $state = $this->decodeState($contents);
                    } catch (RateLimiterException) {
                        continue;
                    }

                    $isExpired = $state['fallback_until'] <= $this->timeDate->timestamp()
                        && $state['window_started_at'] < $cutoff;
                    if ($isExpired) {
                        if (!@unlink($path)) {
                            throw new RateLimiterException('Не удалось удалить просроченное состояние rate limiter');
                        }
                        $removed++;
                    }
                }
            }

            return $removed;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function logGarbageCollectionFailure(Throwable $exception): void
    {
        $message = 'Не удалось очистить устаревшие состояния rate limiter';
        try {
            if ($this->logger !== null) {
                $this->logger->error($message, [
                    'exception' => $exception::class,
                    'message'   => $exception->getMessage(),
                ]);
                return;
            }
        } catch (Throwable $loggingException) {
            $message .= '; ошибка логирования: ' . $loggingException->getMessage();
        }

        error_log($message . ': ' . $exception->getMessage());
    }

    private function getStatePath(string $bucket, string $identifier): string
    {
        $hash = hash('sha256', $bucket . "\0" . $identifier);
        return $this->storageDir . '/' . substr($hash, 0, 2) . '/' . $hash . '.json';
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0o700, true) && !is_dir($path)) {
            throw new RateLimiterException('Не удалось создать каталог файлового rate limiter');
        }
    }

    /**
     * @return array{attempts: int, window_started_at: int, fallback_until: int}
     */
    private function emptyState(): array
    {
        return [
            'attempts'          => 0,
            'window_started_at' => 0,
            'fallback_until'    => 0,
        ];
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
