<?php

declare(strict_types=1);

namespace Tests\Unit;

use DI\Container;
use Invoker\Invoker;
use PHPUnit\Framework\TestCase;
use VEngine\System\Config\Config;
use VEngine\System\Http\Request;
use VEngine\System\Http\Response;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Map\Map;
use VEngine\System\Router\Router;

final class RouterDispatchTest extends TestCase
{
    public function testDispatchesRoutesFromTheMatchingHttpMethodIndex(): void
    {
        $router = new Router(new Container(), new Invoker());
        for ($index = 0; $index < 50; $index++) {
            $router->post('/route-' . $index, static fn() => Response::html('wrong method'));
        }
        $router->get('/target', static fn() => Response::html('matched'));

        $request = new Request(
            new Map(),
            new Config(dirname(__DIR__, 2), new Map()),
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/target'],
            [],
            [],
            '',
        );

        self::assertSame('matched', $this->capture($router->dispatch($request)));
    }

    public function testCompiledRoutesAreAddedToTheMethodIndex(): void
    {
        $source = new Router(new Container(), new Invoker());
        $source->get('/compiled', [CompiledRouteHandler::class, 'handle']);
        $compiled = $source->exportCompiled();
        self::assertNotNull($compiled);

        $router = new Router(new Container(), new Invoker());
        $router->loadCompiled($compiled);
        $request = new Request(
            new Map(),
            new Config(dirname(__DIR__, 2), new Map()),
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/compiled'],
            [],
            [],
            '',
        );

        self::assertSame('compiled', $this->capture($router->dispatch($request)));
    }

    private function capture(ResponseInterface $response): string
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

final class CompiledRouteHandler
{
    public static function handle(): Response
    {
        return Response::html('compiled');
    }
}
