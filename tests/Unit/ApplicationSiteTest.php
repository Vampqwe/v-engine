<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\Kernel\Application;
use VEngine\System\Config\Config;
use VEngine\System\Http\Request;
use VEngine\System\Map\Map;
use VEngine\System\Router\RouterInterface;

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
            'engineEnvExists' => array_key_exists('ENGINE_MODE', $_ENV),
            'engineEnvValue' => $_ENV['ENGINE_MODE'] ?? null,
            'engineServerExists' => array_key_exists('ENGINE_MODE', $_SERVER),
            'engineServerValue' => $_SERVER['ENGINE_MODE'] ?? null,
        ];
        unset($_ENV['APP_SITE'], $_SERVER['APP_SITE'], $_ENV['ENGINE_MODE'], $_SERVER['ENGINE_MODE']);
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_SITE'], $_SERVER['APP_SITE'], $_ENV['ENGINE_MODE'], $_SERVER['ENGINE_MODE']);
        if ($this->previousSite['envExists']) {
            $_ENV['APP_SITE'] = $this->previousSite['envValue'];
        }
        if ($this->previousSite['serverExists']) {
            $_SERVER['APP_SITE'] = $this->previousSite['serverValue'];
        }
        if ($this->previousSite['engineEnvExists']) {
            $_ENV['ENGINE_MODE'] = $this->previousSite['engineEnvValue'];
        }
        if ($this->previousSite['engineServerExists']) {
            $_SERVER['ENGINE_MODE'] = $this->previousSite['engineServerValue'];
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
        self::assertSame('default', $container->get('path.engineMode'));
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

    public function testStaticModeUsesPagesAndDoesNotRequirePhpRouteDirectories(): void
    {
        $root = sys_get_temp_dir() . '/vengine-static-app-' . bin2hex(random_bytes(6));
        $siteRoot = $root . '/sites/StaticSite';
        mkdir($siteRoot . '/pages', 0o700, true);
        mkdir($siteRoot . '/public', 0o700, true);
        mkdir($root . '/config', 0o700, true);
        mkdir($root . '/storage/cache/sites/StaticSite', 0o700, true);
        file_put_contents($root . '/config/dependency-rules.php', "<?php\nreturn [];\n");
        $previousAppEnv = $_ENV['APP_ENV'] ?? null;
        $hadAppEnv = array_key_exists('APP_ENV', $_ENV);
        $_ENV['APP_ENV'] = 'dev';
        $_ENV['APP_SITE'] = 'StaticSite';
        $_ENV['ENGINE_MODE'] = 'static';

        try {
            $container = (new Application($root))->getContainer();
            self::assertSame('static', $container->get('path.engineMode'));
            self::assertSame($siteRoot . '/pages', $container->get('path.pages'));
        } finally {
            unset($_ENV['APP_SITE'], $_ENV['ENGINE_MODE']);
            if ($hadAppEnv) {
                $_ENV['APP_ENV'] = $previousAppEnv;
            } else {
                unset($_ENV['APP_ENV']);
            }
            unlink($root . '/config/dependency-rules.php');
            rmdir($root . '/storage/cache/sites/StaticSite');
            rmdir($root . '/storage/cache/sites');
            rmdir($root . '/storage/cache');
            rmdir($root . '/storage');
            rmdir($root . '/config');
            rmdir($siteRoot . '/pages');
            rmdir($siteRoot . '/public');
            rmdir($siteRoot);
            rmdir($root . '/sites');
            rmdir($root);
        }
    }

    public function testStaticModeBuildsRouterFromSiteJsonMaps(): void
    {
        $previousAppEnv = $_ENV['APP_ENV'] ?? null;
        $hadAppEnv = array_key_exists('APP_ENV', $_ENV);
        $_ENV['APP_SITE'] = 'Corporate';
        $_ENV['ENGINE_MODE'] = 'static';
        $_ENV['APP_ENV'] = 'dev';

        try {
            $router = (new Application(dirname(__DIR__, 2)))->getContainer()->get(RouterInterface::class);
            $request = new Request(
                new Map(),
                new Config(dirname(__DIR__, 2), new Map()),
                ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/about-us'],
                [],
                [],
                '',
            );

            ob_start();
            try {
                $router->dispatch($request)->send();
                $body = (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }

            self::assertStringContainsString('Пример HTML-страницы', $body);
        } finally {
            if ($hadAppEnv) {
                $_ENV['APP_ENV'] = $previousAppEnv;
            } else {
                unset($_ENV['APP_ENV']);
            }
        }
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
