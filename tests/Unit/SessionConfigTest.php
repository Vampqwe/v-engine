<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SessionConfigTest extends TestCase
{
    public function testSecureCookieDefaultsToEnabledInProduction(): void
    {
        $environment = $this->replaceEnvironment(['APP_ENV' => 'prod']);

        try {
            $config = require dirname(__DIR__, 2) . '/config/session.php';
            self::assertTrue($config['secure']);
        } finally {
            $this->restoreEnvironment($environment);
        }
    }

    public function testSecureCookieCanBeExplicitlyDisabled(): void
    {
        $environment = $this->replaceEnvironment([
            'APP_ENV'        => 'prod',
            'SESSION_SECURE' => 'false',
        ]);

        try {
            $config = require dirname(__DIR__, 2) . '/config/session.php';
            self::assertFalse($config['secure']);
        } finally {
            $this->restoreEnvironment($environment);
        }
    }

    public function testRejectsInvalidSecureCookieValue(): void
    {
        $environment = $this->replaceEnvironment([
            'APP_ENV'        => 'prod',
            'SESSION_SECURE' => 'sometimes',
        ]);

        try {
            $this->expectException(\RuntimeException::class);
            require dirname(__DIR__, 2) . '/config/session.php';
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
        $previous = [];
        foreach (['APP_ENV', 'SESSION_SECURE'] as $key) {
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
