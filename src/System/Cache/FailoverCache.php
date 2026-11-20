<?php

declare(strict_types=1);

namespace VEngine\System\Cache;

use Throwable;
use VEngine\System\Exception\BaseException;
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
        if (!$this->recoverPendingWrites()) {
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

        if (!$this->recoverPendingWrites()) {
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

        $this->mirrorSet($key, $value, $ttl);
        return true;
    }

    public function delete(string $key): bool
    {
        if (!$this->recoverPendingWrites()) {
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

        $this->mirrorDelete($key);
        return true;
    }

    private function recoverPendingWrites(): bool
    {
        try {
            $this->journal->replay(function (array $entry): void {
                if ($entry['operation'] === 'delete') {
                    if (!$this->primary->delete($entry['key'])) {
                        throw new CacheException('Не удалось восстановить удаление кэш-ключа в Redis');
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

                if ($ttl > 0) {
                    $this->mirrorSet($entry['key'], $entry['value'], $ttl);
                } else {
                    $this->mirrorDelete($entry['key']);
                }
            });

            return true;
        } catch (CacheException $e) {
            $this->logFailover($e);
            return false;
        }
    }

    private function writeToFallback(string $key, mixed $value, int $ttl): bool
    {
        $written = $this->fallback->set($key, $value, $ttl);
        if (!$written) {
            throw new CacheException('Не удалось записать значение в файловый failover-кэш');
        }
        $this->journal->recordSet($key, $value, $ttl);
        return true;
    }

    private function deleteFromFallback(string $key): bool
    {
        $deleted = $this->fallback->delete($key);
        if (!$deleted) {
            throw new CacheException('Не удалось удалить значение из файлового failover-кэша');
        }
        $this->journal->recordDelete($key);
        return true;
    }

    private function mirrorSet(string $key, mixed $value, int $ttl): void
    {
        try {
            if (!$this->fallback->set($key, $value, $ttl)) {
                $this->logger->error('Не удалось обновить файловую копию кэша');
            }
        } catch (BaseException $e) {
            $this->logger->error('Не удалось обновить файловую копию кэша', [
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);
        }
    }

    private function mirrorDelete(string $key): void
    {
        try {
            if (!$this->fallback->delete($key)) {
                $this->logger->error('Не удалось обновить файловую копию кэша после удаления');
            }
        } catch (BaseException $e) {
            $this->logger->error('Не удалось обновить файловую копию кэша после удаления', [
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);
        }
    }

    private function logFailover(Throwable $e): void
    {
        $this->logger->error('Redis недоступен; кэш переключён на файловое хранилище', [
            'exception' => $e::class,
            'message'   => $e->getMessage(),
        ]);
    }
}
