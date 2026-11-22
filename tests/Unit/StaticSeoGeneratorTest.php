<?php

declare(strict_types=1);

namespace Tests\Unit;

use DI\Container;
use Invoker\Invoker;
use Modules\Seo\Exception\SeoException;
use Modules\Seo\StaticSeoGenerator;
use PHPUnit\Framework\TestCase;
use VEngine\System\Config\Config;
use VEngine\System\Http\Request;
use VEngine\System\Map\Map;
use VEngine\System\Router\Router;
use VEngine\System\Router\StaticSiteContext;

final class StaticSeoGeneratorTest extends TestCase
{
    private string $publicPath;

    protected function setUp(): void
    {
        $this->publicPath = sys_get_temp_dir() . '/vengine-static-seo-' . bin2hex(random_bytes(6));
        mkdir($this->publicPath, 0o700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->publicPath . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->publicPath);
    }

    public function testGeneratesCanonicalSitemapAndRobotsFilesAndRoutes(): void
    {
        $router = new Router(new Container(), new Invoker());
        $context = new StaticSiteContext(
            $router,
            $this->publicPath,
            'https://example.test',
            ['/' => 'index.html', '/about' => 'about.html'],
            ['/about-us' => '/about'],
        );

        (new StaticSeoGenerator())->generate($context);

        $sitemap = (string) file_get_contents($this->publicPath . '/sitemap.xml');
        $robots = (string) file_get_contents($this->publicPath . '/robots.txt');
        self::assertStringContainsString('<loc>https://example.test/</loc>', $sitemap);
        self::assertStringContainsString('<loc>https://example.test/about</loc>', $sitemap);
        self::assertStringNotContainsString('/about-us', $sitemap);
        self::assertSame(
            "User-agent: *\nAllow: /\nSitemap: https://example.test/sitemap.xml\n",
            $robots,
        );
        self::assertSame($sitemap, $this->dispatchBody($router, '/sitemap.xml'));
        self::assertSame($robots, $this->dispatchBody($router, '/robots.txt'));
    }

    public function testRejectsInvalidBaseUrlAndReservedRouteCollision(): void
    {
        $generator = new StaticSeoGenerator();
        $router = new Router(new Container(), new Invoker());

        try {
            $generator->generate(new StaticSiteContext($router, $this->publicPath, 'javascript:alert(1)', [], []));
            self::fail('Expected invalid APP_URL rejection');
        } catch (SeoException $exception) {
            self::assertStringContainsString('APP_URL', $exception->getMessage());
        }

        $this->expectException(SeoException::class);
        $generator->generate(new StaticSiteContext(
            $router,
            $this->publicPath,
            'https://example.test',
            ['/sitemap.xml' => 'index.html'],
            [],
        ));
    }

    public function testRefusesToReplaceSeoFileSymlinks(): void
    {
        $outsideFile = $this->publicPath . '-outside';
        file_put_contents($outsideFile, 'do not overwrite');
        symlink($outsideFile, $this->publicPath . '/sitemap.xml');

        try {
            $context = new StaticSiteContext(
                new Router(new Container(), new Invoker()),
                $this->publicPath,
                'https://example.test',
                ['/' => 'index.html'],
                [],
            );

            $this->expectException(SeoException::class);
            $this->expectExceptionMessage('symlink');
            (new StaticSeoGenerator())->generate($context);
        } finally {
            unlink($outsideFile);
        }
    }

    private function dispatchBody(Router $router, string $path): string
    {
        $request = new Request(
            new Map(),
            new Config(dirname(__DIR__, 2), new Map()),
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path],
            [],
            [],
            '',
        );

        ob_start();
        try {
            $router->dispatch($request)->send();
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
