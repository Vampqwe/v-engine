<?php

declare(strict_types=1);

namespace Tests\Unit;

use DI\ContainerBuilder;
use Invoker\Invoker;
use Invoker\InvokerInterface;
use PHPUnit\Framework\TestCase;
use VEngine\System\Config\Config;
use VEngine\System\Http\Request;
use VEngine\System\Http\Response;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Map\Map;
use VEngine\System\Router\RouteNotFoundException;
use VEngine\System\Router\RouterInterface;

final class RouteCacheInvalidationTest extends TestCase
{
    private string $directory;
    private string $routesDirectory;
    private string $modulesDirectory;
    private string $cacheDirectory;
    private bool $hadAppEnvironment;
    private mixed $previousAppEnvironment;

    protected function setUp(): void
    {
        $this->hadAppEnvironment = array_key_exists('APP_ENV', $_ENV);
        $this->previousAppEnvironment = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'prod';

        $this->directory = sys_get_temp_dir() . '/vengine-route-cache-' . bin2hex(random_bytes(6));
        $this->routesDirectory = $this->directory . '/routes';
        $this->modulesDirectory = $this->directory . '/modules';
        $this->cacheDirectory = $this->directory . '/cache';
        mkdir($this->routesDirectory, 0o700, true);
        mkdir($this->modulesDirectory, 0o700, true);
        mkdir($this->cacheDirectory, 0o700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->routesDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->cacheDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->routesDirectory);
        rmdir($this->modulesDirectory);
        rmdir($this->cacheDirectory);
        rmdir($this->directory);
        if ($this->hadAppEnvironment) {
            $_ENV['APP_ENV'] = $this->previousAppEnvironment;
        } else {
            unset($_ENV['APP_ENV']);
        }
    }

    public function testChangingRouteFileInvalidatesTheCompiledProductionCache(): void
    {
        $routeFile = $this->routesDirectory . '/web.php';
        file_put_contents($routeFile, $this->registrar('/before'));

        $firstRouter = $this->makeRouter();
        self::assertSame('before', $this->dispatch($firstRouter, '/before'));

        file_put_contents($routeFile, $this->registrar('/after'));
        $secondRouter = $this->makeRouter();

        self::assertSame('after', $this->dispatch($secondRouter, '/after'));
        $this->expectException(RouteNotFoundException::class);
        $this->dispatch($secondRouter, '/before');
    }

    private function makeRouter(): RouterInterface
    {
        $builder = new ContainerBuilder();
        $builder->addDefinitions([
            'path.cache'   => $this->cacheDirectory,
            'path.log'     => $this->directory . '/log',
            'path.root'    => dirname(__DIR__, 2),
            'path.routes'  => $this->routesDirectory,
            'path.modules' => $this->modulesDirectory,
            InvokerInterface::class => new Invoker(),
        ]);
        $builder->addDefinitions(dirname(__DIR__, 2) . '/config/dependency-rules.php');

        return $builder->build()->get(RouterInterface::class);
    }

    private function registrar(string $path): string
    {
        $handler = $path === '/after' ? 'after' : 'before';
        return sprintf(
            '<?php return static function (\\VEngine\\System\\Router\\RouterInterface $router): void { $router->get(%s, [\\Tests\\Unit\\RouteCacheHandler::class, %s]); };',
            var_export($path, true),
            var_export($handler, true),
        );
    }

    private function dispatch(RouterInterface $router, string $path): string
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
            $response = $router->dispatch($request);
            self::assertInstanceOf(ResponseInterface::class, $response);
            $response->send();
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}

final class RouteCacheHandler
{
    public static function before(): Response
    {
        return Response::html('before');
    }

    public static function after(): Response
    {
        return Response::html('after');
    }
}
