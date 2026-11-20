<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\Kernel\Application;

final class ApplicationSiteTest extends TestCase
{
    private array $previousSite;

    protected function setUp(): void
    {
        $this->previousSite = [
            'envExists'    => array_key_exists('APP_SITE', $_ENV),
            'envValue'     => $_ENV['APP_SITE'] ?? null,
            'serverExists' => array_key_exists('APP_SITE', $_SERVER),
            'serverValue'  => $_SERVER['APP_SITE'] ?? null,
        ];
        unset($_ENV['APP_SITE'], $_SERVER['APP_SITE']);
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_SITE'], $_SERVER['APP_SITE']);
        if ($this->previousSite['envExists']) {
            $_ENV['APP_SITE'] = $this->previousSite['envValue'];
        }
        if ($this->previousSite['serverExists']) {
            $_SERVER['APP_SITE'] = $this->previousSite['serverValue'];
        }
    }

    public function testSelectsTheDefaultSiteAndExposesSitePaths(): void
    {
        $application = new Application(dirname(__DIR__, 2));
        $container = $application->getContainer();

        self::assertSame('Corporate', $container->get('path.site'));
        self::assertSame(dirname(__DIR__, 2) . '/sites/Corporate', $container->get('path.siteRoot'));
        self::assertSame(
            dirname(__DIR__, 2) . '/sites/Corporate/public',
            $container->get('path.public'),
        );
        self::assertSame(
            dirname(__DIR__, 2) . '/sites/Corporate/App',
            $container->get('path.siteApp'),
        );
        self::assertSame(
            dirname(__DIR__, 2) . '/storage/cache/sites/Corporate',
            $container->get('path.cache'),
        );
    }

    public function testRejectsPathTraversalInSiteName(): void
    {
        $_ENV['APP_SITE'] = '../config';

        $this->expectException(\InvalidArgumentException::class);
        new Application(dirname(__DIR__, 2));
    }

    public function testRejectsUnknownSite(): void
    {
        $_ENV['APP_SITE'] = 'MissingSite';

        $this->expectException(\InvalidArgumentException::class);
        new Application(dirname(__DIR__, 2));
    }

    public function testReportsWhenProductionDiCacheDirectoryCannotBeCreated(): void
    {
        $root = sys_get_temp_dir() . '/vengine-app-cache-' . bin2hex(random_bytes(6));
        $siteRoot = $root . '/sites/TestSite';
        foreach (['App', 'routes', 'views', 'public'] as $directory) {
            mkdir($siteRoot . '/' . $directory, 0o700, true);
        }
        mkdir($root . '/storage', 0o700, true);
        $storageBlocker = $root . '/storage/cache';
        file_put_contents($storageBlocker, 'not a directory');

        $previousEnv = $_ENV['APP_ENV'] ?? null;
        $hadEnv = array_key_exists('APP_ENV', $_ENV);
        $_ENV['APP_ENV'] = 'prod';
        $_ENV['APP_SITE'] = 'TestSite';

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Не удалось создать каталог кэша DI');
            new Application($root);
        } finally {
            if ($hadEnv) {
                $_ENV['APP_ENV'] = $previousEnv;
            } else {
                unset($_ENV['APP_ENV']);
            }
            unlink($storageBlocker);
            rmdir($root . '/storage');
            foreach (['App', 'routes', 'views', 'public'] as $directory) {
                rmdir($siteRoot . '/' . $directory);
            }
            rmdir($siteRoot);
            rmdir($root . '/sites');
            rmdir($root);
        }
    }
}
