<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\System\Cache\CacheException;
use VEngine\System\Cache\CacheFailoverJournal;
use VEngine\System\Cache\CacheInterface;
use VEngine\System\Cache\FailoverCache;
use VEngine\System\Cache\FileCache;
use VEngine\System\File\File;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\TimeDate\TimeDate;

final class FailoverCacheTest extends TestCase
{
    private string $directory;
    private TestCacheStore $primary;
    private FailoverCache $cache;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/vengine-failover-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0o700, true);

        $file = new File();
        $timeDate = new TimeDate();
        $this->primary = new TestCacheStore();
        $fallback = new FileCache($this->directory . '/data', $file, $timeDate);
        $journal = new CacheFailoverJournal($this->directory . '/pending.dat', $file);
        $logger = new class implements LoggerInterface {
            public function forModule(string $moduleName): self
            {
                return $this;
            }

            public function info(string $message, array $context = []): void {}

            public function error(string $message, array $context = []): void {}
        };

        $this->cache = new FailoverCache($this->primary, $fallback, $journal, $timeDate, $logger);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            if (is_dir($path)) {
                foreach (glob($path . '/*') ?: [] as $child) {
                    unlink($child);
                }
                rmdir($path);
                continue;
            }
            unlink($path);
        }

        rmdir($this->directory);
    }

    public function testOfflineWritesAreServedFromFilesAndReplayedToRedis(): void
    {
        $this->primary->available = false;

        self::assertTrue($this->cache->set('offline-key', ['value' => 42], 60));
        self::assertSame(['value' => 42], $this->cache->get('offline-key'));

        $this->primary->available = true;
        self::assertSame(['value' => 42], $this->cache->get('offline-key'));
        self::assertSame(['value' => 42], $this->primary->get('offline-key'));
        self::assertGreaterThan(0, $this->primary->remainingTtl('offline-key'));
        self::assertLessThanOrEqual(60, $this->primary->remainingTtl('offline-key'));
    }

    public function testOfflineDeletesAreReplayedWithoutResurrectingTheKey(): void
    {
        $this->cache->set('delete-key', 'present', 60);
        $this->primary->available = false;
        $this->cache->delete('delete-key');

        $this->primary->available = true;

        self::assertSame('missing', $this->cache->get('delete-key', 'missing'));
        self::assertSame('missing', $this->primary->get('delete-key', 'missing'));
    }
}

final class TestCacheStore implements CacheInterface
{
    public bool $available = true;
    private array $values = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertAvailable();
        if (!isset($this->values[$key]) || $this->values[$key]['expires_at'] < time()) {
            return $default;
        }

        return $this->values[$key]['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $this->assertAvailable();
        $this->values[$key] = [
            'value'      => $value,
            'expires_at' => time() + $ttl,
        ];
        return true;
    }

    public function delete(string $key): bool
    {
        $this->assertAvailable();
        unset($this->values[$key]);
        return true;
    }

    public function remainingTtl(string $key): int
    {
        return $this->values[$key]['expires_at'] - time();
    }

    private function assertAvailable(): void
    {
        if (!$this->available) {
            throw new CacheException('Test Redis is unavailable');
        }
    }
}
