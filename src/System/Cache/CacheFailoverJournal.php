<?php

declare(strict_types=1);

namespace VEngine\System\Cache;

use Throwable;
use VEngine\System\File\FileInterface;

final class CacheFailoverJournal
{
    public function __construct(
        private readonly string $path,
        private readonly FileInterface $file,
    ) {}

    public function hasPending(): bool
    {
        if (!is_file($this->path)) {
            return false;
        }

        $size = filesize($this->path);
        if ($size === false) {
            throw new CacheException('Не удалось проверить журнал failover-кэша');
        }

        return $size > 0;
    }

    public function recordSetWithFallback(string $key, mixed $value, int $ttl, callable $persistFallback): void
    {
        if ($ttl <= 0) {
            $this->recordDeleteWithFallback($key, $persistFallback);
            return;
        }

        $this->update($key, [
            'key'        => $key,
            'operation'  => 'set',
            'value'      => $value,
            'expires_at' => time() + $ttl,
        ], $persistFallback);
    }

    public function recordDeleteWithFallback(string $key, callable $persistFallback): void
    {
        $this->update($key, [
            'key'       => $key,
            'operation' => 'delete',
        ], $persistFallback);
    }

    public function replay(callable $apply, int $maxEntries = 10): bool
    {
        if ($maxEntries < 1) {
            throw new \InvalidArgumentException('Размер пакета replay должен быть положительным');
        }

        return $this->withLock(function () use ($apply, $maxEntries): bool {
            $entries = $this->readEntries();
            $batch = array_slice($entries, 0, $maxEntries, true);

            foreach ($batch as $hash => $entry) {
                $apply($entry);
                unset($entries[$hash]);
            }

            if ($entries === []) {
                if (is_file($this->path) && !unlink($this->path)) {
                    throw new CacheException('Не удалось очистить журнал failover-кэша после восстановления');
                }
                return true;
            }

            $this->writeEntries($entries);
            return false;
        });
    }

    private function update(string $key, array $entry, ?callable $beforeWrite = null): void
    {
        $this->withLock(function () use ($key, $entry, $beforeWrite): void {
            if ($beforeWrite !== null) {
                $beforeWrite();
            }
            $entries = $this->readEntries();
            $entries[hash('sha256', $key)] = $entry;
            $this->writeEntries($entries);
        });
    }

    private function readEntries(): array
    {
        if (!$this->file->exists($this->path)) {
            return [];
        }

        try {
            $entries = unserialize($this->file->read($this->path), ['allowed_classes' => false]);
        } catch (Throwable $e) {
            throw new CacheException('Не удалось прочитать журнал failover-кэша', 0, $e);
        }

        if (!is_array($entries)) {
            throw new CacheException('Повреждён журнал failover-кэша');
        }

        foreach ($entries as $hash => $entry) {
            if (
                !is_string($hash)
                || !is_array($entry)
                || !isset($entry['key'], $entry['operation'])
                || !is_string($entry['key'])
                || hash('sha256', $entry['key']) !== $hash
                || !in_array($entry['operation'], ['set', 'delete'], true)
                || ($entry['operation'] === 'set' && !array_key_exists('value', $entry))
                || ($entry['operation'] === 'set' && !isset($entry['expires_at']))
                || ($entry['operation'] === 'set' && !is_int($entry['expires_at']))
            ) {
                throw new CacheException('Повреждена запись в журнале failover-кэша');
            }
        }

        return $entries;
    }

    private function writeEntries(array $entries): void
    {
        $temporaryPath = $this->path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $this->file->write($temporaryPath, serialize($entries));

        if (!rename($temporaryPath, $this->path)) {
            if ($this->file->exists($temporaryPath)) {
                unlink($temporaryPath);
            }
            throw new CacheException('Не удалось атомарно сохранить журнал failover-кэша');
        }
    }

    private function withLock(callable $callback): mixed
    {
        $this->file->ensureDirectory(dirname($this->path));
        $lock = fopen($this->path . '.lock', 'c');
        if ($lock === false) {
            throw new CacheException('Не удалось открыть блокировку журнала failover-кэша');
        }

        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new CacheException('Не удалось заблокировать журнал failover-кэша');
        }

        try {
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
