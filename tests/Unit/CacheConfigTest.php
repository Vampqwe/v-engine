<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CacheConfigTest extends TestCase
{
    public function testCacheDriverDefaultsToFile(): void
    {
        $environment = $this->replaceCacheDriver(null);

        try {
            $config = require dirname(__DIR__, 2) . '/config/cache.php';
            self::assertSame('file', $config['driver']);
        } finally {
            $this->restoreCacheDriver($environment);
        }
    }

    public function testRejectsUnsupportedCacheDriver(): void
    {
        $environment = $this->replaceCacheDriver('memcached');

        try {
            $this->expectException(\RuntimeException::class);
            require dirname(__DIR__, 2) . '/config/cache.php';
        } finally {
            $this->restoreCacheDriver($environment);
        }
    }

    /**
     * @return array{envExists: bool, envValue: mixed, serverExists: bool, serverValue: mixed}
     */
    private function replaceCacheDriver(?string $driver): array
    {
        $previous = [
            'envExists'    => array_key_exists('CACHE_DRIVER', $_ENV),
            'envValue'     => $_ENV['CACHE_DRIVER'] ?? null,
            'serverExists' => array_key_exists('CACHE_DRIVER', $_SERVER),
            'serverValue'  => $_SERVER['CACHE_DRIVER'] ?? null,
        ];

        unset($_ENV['CACHE_DRIVER'], $_SERVER['CACHE_DRIVER']);
        if ($driver !== null) {
            $_ENV['CACHE_DRIVER'] = $driver;
        }

        return $previous;
    }

    /**
     * @param array{envExists: bool, envValue: mixed, serverExists: bool, serverValue: mixed} $previous
     */
    private function restoreCacheDriver(array $previous): void
    {
        unset($_ENV['CACHE_DRIVER'], $_SERVER['CACHE_DRIVER']);
        if ($previous['envExists']) {
            $_ENV['CACHE_DRIVER'] = $previous['envValue'];
        }
        if ($previous['serverExists']) {
            $_SERVER['CACHE_DRIVER'] = $previous['serverValue'];
        }
    }
}
