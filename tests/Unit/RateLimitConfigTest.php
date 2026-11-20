<?php

declare(strict_types=1);

namespace Tests\Unit;

use DI\ContainerBuilder;
use PHPUnit\Framework\TestCase;
use VEngine\System\RateLimit\FileRateLimiter;
use VEngine\System\RateLimit\RateLimiterInterface;

final class RateLimitConfigTest extends TestCase
{
    public function testRateLimiterDriverDefaultsToRedis(): void
    {
        $environment = $this->replaceDriver(null);

        try {
            $config = require dirname(__DIR__, 2) . '/config/rate-limit.php';
            self::assertSame('redis', $config['driver']);
        } finally {
            $this->restoreDriver($environment);
        }
    }

    public function testSelectsFileRateLimiterDriver(): void
    {
        $environment = $this->replaceDriver('file');

        try {
            $config = require dirname(__DIR__, 2) . '/config/rate-limit.php';
            self::assertSame('file', $config['driver']);
        } finally {
            $this->restoreDriver($environment);
        }
    }

    public function testFileDriverResolvesWithoutConstructingRedisRateLimiter(): void
    {
        $environment = $this->replaceDriver('file');

        try {
            $builder = new ContainerBuilder();
            $builder->addDefinitions([
                'path.cache' => sys_get_temp_dir() . '/vengine-rate-limiter-test',
                'path.root'  => dirname(__DIR__, 2),
            ]);
            $builder->addDefinitions(dirname(__DIR__, 2) . '/config/dependency-rules.php');

            self::assertInstanceOf(
                FileRateLimiter::class,
                $builder->build()->get(RateLimiterInterface::class),
            );
        } finally {
            $this->restoreDriver($environment);
        }
    }

    public function testRejectsUnsupportedRateLimiterDriver(): void
    {
        $environment = $this->replaceDriver('memcached');

        try {
            $this->expectException(\RuntimeException::class);
            require dirname(__DIR__, 2) . '/config/rate-limit.php';
        } finally {
            $this->restoreDriver($environment);
        }
    }

    /**
     * @return array{envExists: bool, envValue: mixed, serverExists: bool, serverValue: mixed}
     */
    private function replaceDriver(?string $driver): array
    {
        $previous = [
            'envExists'    => array_key_exists('RATE_LIMIT_DRIVER', $_ENV),
            'envValue'     => $_ENV['RATE_LIMIT_DRIVER'] ?? null,
            'serverExists' => array_key_exists('RATE_LIMIT_DRIVER', $_SERVER),
            'serverValue'  => $_SERVER['RATE_LIMIT_DRIVER'] ?? null,
        ];

        unset($_ENV['RATE_LIMIT_DRIVER'], $_SERVER['RATE_LIMIT_DRIVER']);
        if ($driver !== null) {
            $_ENV['RATE_LIMIT_DRIVER'] = $driver;
        }

        return $previous;
    }

    /**
     * @param array{envExists: bool, envValue: mixed, serverExists: bool, serverValue: mixed} $previous
     */
    private function restoreDriver(array $previous): void
    {
        unset($_ENV['RATE_LIMIT_DRIVER'], $_SERVER['RATE_LIMIT_DRIVER']);
        if ($previous['envExists']) {
            $_ENV['RATE_LIMIT_DRIVER'] = $previous['envValue'];
        }
        if ($previous['serverExists']) {
            $_SERVER['RATE_LIMIT_DRIVER'] = $previous['serverValue'];
        }
    }
}
