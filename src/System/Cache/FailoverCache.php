<?php

declare(strict_types=1);

namespace VEngine\System\Cache;

use Throwable;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\TimeDate\TimeDateInterface;

final class FailoverCache implements CacheInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly CacheInterface $primary,
        private readonly CacheInterface $fallback,
        private readonly CacheFailoverJournal $journal,
        private readonly TimeDateInterface $timeDate,
        LoggerInterface $logger,
    ) {
        $this->logger = $logger->forModule('Cache');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->journal->hasPending() && !$this->recoverPendingWrites()) {
            return $this->fallback->get($key, $default);
        }

        try {
            return $this->primary->get($key, $default);
        } catch (CacheException $e) {
            $this->logFailover($e);
            return $this->fallback->get($key, $default);
        }
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        if ($ttl <= 0) {
            return $this->delete($key);
        }

        if ($this->journal->hasPending() && !$this->recoverPendingWrites()) {
            return $this->writeToFallback($key, $value, $ttl);
        }

        try {
            $written = $this->primary->set($key, $value, $ttl);
        } catch (CacheException $e) {
            $this->logFailover($e);
            return $this->writeToFallback($key, $value, $ttl);
        }

        if (!$written) {
            return $this->writeToFallback($key, $value, $ttl);
        }

        return true;
    }

    public function delete(string $key): bool
    {
        if ($this->journal->hasPending() && !$this->recoverPendingWrites()) {
            return $this->deleteFromFallback($key);
        }

        try {
            $deleted = $this->primary->delete($key);
        } catch (CacheException $e) {
            $this->logFailover($e);
            return $this->deleteFromFallback($key);
        }

        if (!$deleted) {
            return $this->deleteFromFallback($key);
        }

        return true;
    }

    private function recoverPendingWrites(): bool
    {
        try {
            return $this->journal->replay(function (array $entry): void {
                if ($entry['operation'] === 'delete') {
                    if (!$this->primary->delete($entry['key'])) {
                        throw new CacheException('Не удалось восстановить удаление кэш-ключа в Redis');
                    }
                    if (!$this->fallback->delete($entry['key'])) {
                        throw new CacheException('Не удалось очистить файловую копию удалённого ключа');
                    }
                    return;
                }

                $ttl = $entry['expires_at'] - $this->timeDate->timestamp();
                $written = $ttl > 0
                    ? $this->primary->set($entry['key'], $entry['value'], $ttl)
                    : $this->primary->delete($entry['key']);

                if (!$written) {
                    throw new CacheException('Не удалось восстановить кэш-ключ в Redis');
                }

                $fallbackUpdated = $ttl > 0
                    ? $this->fallback->set($entry['key'], $entry['value'], $ttl)
                    : $this->fallback->delete($entry['key']);
                if (!$fallbackUpdated) {
                    throw new CacheException('Не удалось обновить файловую копию восстановленного ключа');
                }
            });
        } catch (CacheException $e) {
            $this->logFailover($e);
            return false;
        }
    }

    private function writeToFallback(string $key, mixed $value, int $ttl): bool
    {
        $this->journal->recordSetWithFallback($key, $value, $ttl, function () use ($key, $value, $ttl): void {
            if (!$this->fallback->set($key, $value, $ttl)) {
                throw new CacheException('Не удалось записать значение в файловый failover-кэш');
            }
        });
        return true;
    }

    private function deleteFromFallback(string $key): bool
    {
        $this->journal->recordDeleteWithFallback($key, function () use ($key): void {
            if (!$this->fallback->delete($key)) {
                throw new CacheException('Не удалось удалить значение из файлового failover-кэша');
            }
        });
        return true;
    }

    private function logFailover(Throwable $e): void
    {
        $this->logger->error('Redis недоступен; кэш переключён на файловое хранилище', [
            'exception' => $e::class,
            'message'   => $e->getMessage(),
        ]);
    }
}
