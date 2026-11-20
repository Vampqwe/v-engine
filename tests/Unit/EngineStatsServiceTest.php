<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Admin\Services\EngineStatsService;
use PHPUnit\Framework\TestCase;
use VEngine\System\Config\ConfigInterface;
use VEngine\System\DataBase\DataBaseException;
use VEngine\System\DataBase\DataBaseInterface;
use VEngine\System\Redis\RedisClient;

final class EngineStatsServiceTest extends TestCase
{
    public function testCollectsSiteStructureAndReportsUnavailableDatabase(): void
    {
        $stats = $this->collectStats('file');

        self::assertSame('Corporate', $stats['application']['site']);
        self::assertSame('unavailable', $stats['database']['status']);
        self::assertNull($stats['database']['admin_users']);
        self::assertContains('Admin', $stats['structure']['modules']);
        self::assertGreaterThan(0, $stats['structure']['route_files']);
        self::assertArrayHasKey('memory_peak', $stats['runtime']);
    }

    public function testReportsRedisAsUsedByRateLimiter(): void
    {
        $stats = $this->collectStats('redis');

        self::assertNotSame('not_used', $stats['cache']['redis_status']);
        self::assertContains('rate limiter', $stats['cache']['redis_usage']);
    }

    private function collectStats(string $rateLimitDriver): array
    {
        $root = dirname(__DIR__, 2);
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturnCallback(
            static fn(string $file, string $key, mixed $default = null): mixed => match ("{$file}.{$key}") {
                'app.name' => 'V-engine',
                'cache.driver', 'session.driver' => 'file',
                'rate-limit.driver' => $rateLimitDriver,
                default => $default,
            },
        );
        $config->method('env')->willReturn('dev');

        $database = $this->createMock(DataBaseInterface::class);
        $database->method('query')->willThrowException(new DataBaseException('database unavailable'));

        return (new EngineStatsService(
            $config,
            $database,
            new RedisClient($config),
            $root . '/sites/Corporate',
            $root . '/sites',
            $root . '/modules',
            $root . '/storage',
        ))->collect();
    }
}
