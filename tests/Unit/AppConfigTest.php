<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    public function testDebugIsDisabledInProductionEvenWhenExplicitlyRequested(): void
    {
        $environment = $this->replaceEnvironment([
            'APP_ENV'   => 'prod',
            'APP_DEBUG' => 'true',
        ]);

        try {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            self::assertFalse($config['debug']);
        } finally {
            $this->restoreEnvironment($environment);
        }
    }

    public function testDebugCanBeEnabledInDevelopment(): void
    {
        $environment = $this->replaceEnvironment([
            'APP_ENV'   => 'dev',
            'APP_DEBUG' => 'true',
        ]);

        try {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            self::assertTrue($config['debug']);
        } finally {
            $this->restoreEnvironment($environment);
        }
    }

    public function testTrustedProxyListDefaultsToEmpty(): void
    {
        $environment = $this->replaceEnvironment([]);

        try {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            self::assertSame([], $config['trusted_proxies']);
        } finally {
            $this->restoreEnvironment($environment);
        }
    }

    /**
     * @param array<string, string> $values
     * @return array<string, array{envExists: bool, envValue: mixed, serverExists: bool, serverValue: mixed}>
     */
    private function replaceEnvironment(array $values): array
    {
        $keys = ['APP_ENV', 'APP_DEBUG', 'TRUSTED_PROXIES'];
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

        foreach ($values as $key => $value) {
            $_ENV[$key] = $value;
        }

        return $previous;
    }

    /**
     * @param array<string, array{envExists: bool, envValue: mixed, serverExists: bool, serverValue: mixed}> $previous
     */
    private function restoreEnvironment(array $previous): void
    {
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
