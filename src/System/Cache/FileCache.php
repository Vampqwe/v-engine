<?php

declare(strict_types=1);

namespace VEngine\System\Cache;

use Throwable;
use VEngine\System\File\FileException;
use VEngine\System\File\FileInterface;
use VEngine\System\TimeDate\TimeDateInterface;

final class FileCache extends AbstractCache
{
    public function __construct(
        private readonly string $cacheDir,
        private readonly FileInterface $file,
        TimeDateInterface $timeDate,
    ) {
        parent::__construct($timeDate);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->getPath($key);
        if (!$this->file->exists($path)) {
            return $default;
        }

        try {
            $raw = $this->file->read($path);
        } catch (FileException $e) {
            if (!$this->file->exists($path)) {
                return $default;
            }
            throw $e;
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
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        if ($ttl <= 0) {
            return $this->delete($key);
        }

        $payload = serialize($this->preparePayload($value, $ttl));
        $path = $this->getPath($key);
        $directory = dirname($path);
        $this->file->ensureDirectory($directory);

        $temporaryPath = tempnam($directory, basename($path) . '.');
        if ($temporaryPath === false) {
            throw new CacheException('Не удалось создать временный файл кэша');
        }
        if (dirname($temporaryPath) !== $directory) {
            if (!unlink($temporaryPath)) {
                throw new CacheException('Не удалось удалить временный файл кэша вне целевого каталога');
            }
            throw new CacheException('Каталог кэша недоступен для атомарной записи');
        }

        try {
            $this->file->write($temporaryPath, $payload);
            if (!rename($temporaryPath, $path)) {
                throw new CacheException('Не удалось атомарно сохранить файл кэша');
            }
        } catch (Throwable $e) {
            if (is_file($temporaryPath) && !unlink($temporaryPath)) {
                throw new CacheException('Не удалось удалить временный файл кэша', 0, $e);
            }
            throw $e;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $path = $this->getPath($key);
        return $this->file->exists($path) ? unlink($path) : true;
    }

    private function getPath(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }
}
