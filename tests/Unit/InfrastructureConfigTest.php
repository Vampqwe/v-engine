<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InfrastructureConfigTest extends TestCase
{
    public function testDatabaseAndRedisSettingsAreReadFromEnvironment(): void
    {
        $keys = ['DB_HOST', 'DB_PORT', 'DB_PASS', 'REDIS_TIMEOUT'];
        $previous = [];
        foreach ($keys as $key) {
            $previous[$key] = [
                'envExists'    => array_key_exists($key, $_ENV),
                'envValue'     => $_ENV[$key] ?? null,
                'serverExists' => array_key_exists($key, $_SERVER),
                'serverValue'  => $_SERVER[$key] ?? null,
            ];
            unset($_ENV[$key], $_SERVER[$key]);
        }

        $_ENV['DB_HOST'] = 'database.internal';
        $_ENV['DB_PORT'] = '3307';
        $_ENV['DB_PASS'] = 'test-only-password';
        $_ENV['REDIS_TIMEOUT'] = '4.5';

        try {
            $database = require dirname(__DIR__, 2) . '/config/database.php';
            $redis = require dirname(__DIR__, 2) . '/config/redis.php';

            self::assertSame('database.internal', $database['host']);
            self::assertSame(3307, $database['port']);
            self::assertSame('test-only-password', $database['password']);
            self::assertSame(4.5, $redis['timeout']);
        } finally {
            foreach ($previous as $key => $state) {
                unset($_ENV[$key], $_SERVER[$key]);
                if ($state['envExists']) {
                    $_ENV[$key] = $state['envValue'];
                }
                if ($state['serverExists']) {
                    $_SERVER[$key] = $state['serverValue'];
                }
            }
        }
    }
}
