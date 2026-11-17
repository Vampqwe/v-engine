<?php

declare(strict_types=1);

namespace VEngine\System\Cache;

use VEngine\System\File\FileInterface;
use VEngine\System\TimeDate\TimeDateInterface;

final class FileCache extends AbstractCache
{
    public function __construct(
        private readonly string $cacheDir,
        private readonly FileInterface $file,
        TimeDateInterface $timeDate
    ) {
        parent::__construct($timeDate);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->getPath($key);
        if (!$this->file->exists($path)) {
            return $default;
        }

        $payload = unserialize($this->file->read($path), ['allowed_classes' => false]);
        if (!is_array($payload) || $this->isExpired($payload)) {
            $this->delete($key);
            return $default;
        }

        return $payload['data'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $payload = serialize($this->preparePayload($value, $ttl));
        return $this->file->write($this->getPath($key), $payload);
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
