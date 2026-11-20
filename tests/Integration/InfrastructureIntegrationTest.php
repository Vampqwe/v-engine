<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use VEngine\Kernel\Application;
use VEngine\System\Cache\CacheInterface;
use VEngine\System\DataBase\DataBaseInterface;
use VEngine\System\RateLimit\RateLimiterInterface;

#[Group('integration')]
final class InfrastructureIntegrationTest extends TestCase
{
    public function testMySqlAndRedisBackendsAreReachable(): void
    {
        if (getenv('CI_INTEGRATION') !== '1') {
            self::markTestSkipped('Integration services are enabled only in CI.');
        }

        if (!extension_loaded('redis')) {
            self::fail('CI integration tests require ext-redis.');
        }

        $application = new Application(dirname(__DIR__, 2));
        $container = $application->getContainer();

        /** @var DataBaseInterface $database */
        $database = $container->get(DataBaseInterface::class);
        self::assertSame(1, (int) $database->query('SELECT 1')->fetchColumn());

        /** @var CacheInterface $cache */
        $cache = $container->get(CacheInterface::class);
        $key = 'ci-integration-' . bin2hex(random_bytes(8));
        self::assertTrue($cache->set($key, 'redis-ok', 30));
        self::assertSame('redis-ok', $cache->get($key));
        self::assertTrue($cache->delete($key));

        /** @var RateLimiterInterface $rateLimiter */
        $rateLimiter = $container->get(RateLimiterInterface::class);
        $bucket = 'ci-integration';
        $identifier = bin2hex(random_bytes(8));
        self::assertSame(1, $rateLimiter->hit($bucket, $identifier, 30)->attempts);
        self::assertSame(2, $rateLimiter->hit($bucket, $identifier, 30)->attempts);
        $rateLimiter->clear($bucket, $identifier);
        self::assertSame(1, $rateLimiter->hit($bucket, $identifier, 30)->attempts);
        $rateLimiter->clear($bucket, $identifier);
    }
}
