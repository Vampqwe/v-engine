<?php

declare(strict_types=1);

namespace Tests\Unit;

use DI\Container;
use Invoker\Invoker;
use PHPUnit\Framework\TestCase;
use VEngine\System\Config\Config;
use VEngine\System\Http\Request;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Map\Map;
use VEngine\System\Router\RouteNotFoundException;
use VEngine\System\Router\Router;
use VEngine\System\Router\RouterException;
use VEngine\System\Router\StaticPageController;
use VEngine\System\Router\StaticRouteLoader;

final class StaticRouteLoaderTest extends TestCase
{
    private string $siteRoot;
    private string $pagesPath;
    private string $routesFile;
    private string $aliasesFile;
    private Router $router;

    protected function setUp(): void
    {
        $this->siteRoot = sys_get_temp_dir() . '/vengine-static-site-' . bin2hex(random_bytes(6));
        $this->pagesPath = $this->siteRoot . '/pages';
        mkdir($this->pagesPath, 0o700, true);
        file_put_contents($this->pagesPath . '/index.html', '<h1>Home</h1>');
        file_put_contents($this->pagesPath . '/about.html', '<h1>About</h1>');
        $this->routesFile = $this->siteRoot . '/routes.json';
        $this->aliasesFile = $this->siteRoot . '/route_aliases.json';
        $this->writeRouteFiles(
            ['/' => 'index.html', '/about' => 'about.html'],
            ['/home' => '/', '/about-us' => '/about'],
        );
        $this->router = new Router(new Container(), new Invoker());
        (new StaticRouteLoader())->load(
            $this->router,
            new StaticPageController($this->pagesPath),
            $this->routesFile,
            $this->aliasesFile,
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->pagesPath . '/*.html') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->pagesPath);
        unlink($this->routesFile);
        unlink($this->aliasesFile);
        rmdir($this->siteRoot);
    }

    public function testDispatchesPageAndAliasInternally(): void
    {
        self::assertSame('<h1>About</h1>', $this->body($this->router->dispatch($this->request('/about'))));
        self::assertSame('<h1>About</h1>', $this->body($this->router->dispatch($this->request('/about-us'))));
    }

    public function testHeadReturnsHeadersWithoutPageBody(): void
    {
        $response = $this->router->dispatch($this->request('/', 'HEAD'));

        self::assertSame('', $this->body($response));
    }

    public function testUnknownAndUnsupportedMethodsReturnNotFound(): void
    {
        try {
            $this->router->dispatch($this->request('/missing'));
            self::fail('Expected not found for unknown route');
        } catch (RouteNotFoundException) {
            self::assertTrue(true);
        }

        $this->expectException(RouteNotFoundException::class);
        $this->router->dispatch($this->request('/', 'POST'));
    }

    public function testRejectsAliasTargetsThatAreNotDeclaredRoutes(): void
    {
        $this->writeRouteFiles(['/' => 'index.html'], ['/home' => '/missing']);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('alias target');
        (new StaticRouteLoader())->load(
            new Router(new Container(), new Invoker()),
            new StaticPageController($this->pagesPath),
            $this->routesFile,
            $this->aliasesFile,
        );
    }

    public function testRejectsPageTraversalInRoutesJson(): void
    {
        $this->writeRouteFiles(['/' => '../outside.html'], []);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Некорректный путь страницы');
        (new StaticRouteLoader())->load(
            new Router(new Container(), new Invoker()),
            new StaticPageController($this->pagesPath),
            $this->routesFile,
            $this->aliasesFile,
        );
    }

    private function writeRouteFiles(array $routes, array $aliases): void
    {
        file_put_contents($this->routesFile, json_encode((object) $routes, JSON_THROW_ON_ERROR));
        file_put_contents($this->aliasesFile, json_encode((object) $aliases, JSON_THROW_ON_ERROR));
    }

    private function request(string $path, string $method = 'GET'): Request
    {
        return new Request(
            new Map(),
            new Config(dirname(__DIR__, 2), new Map()),
            ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path],
            [],
            [],
            '',
        );
    }

    private function body(ResponseInterface $response): string
    {
        ob_start();
        try {
            $response->send();
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
