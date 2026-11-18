<?php

declare(strict_types=1);

namespace VEngine\System\Router;

use Invoker\InvokerInterface;
use Psr\Container\ContainerInterface;
use VEngine\System\Middleware\MiddlewareInterface;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;

final class Router implements RouterInterface
{
    /** @var Route[] */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $namedRoutes = [];

    /** @var array<int, array{prefix: string, namePrefix: string, middleware: array}> */
    private array $groupStack = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly InvokerInterface $invoker
    ) {}

    public function get(string $path, callable|array $handler): Route
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): Route
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): Route
    {
        return $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): Route
    {
        return $this->add('DELETE', $path, $handler);
    }

    public function add(string $method, string $path, callable|array $handler): Route
    {
        $prefix = $this->getCurrentPrefix();
        $namePrefix = $this->getCurrentNamePrefix();
        $middlewares = $this->getCurrentMiddlewares();

        $fullPath = $this->normalizePath($prefix . '/' . ltrim($path, '/'));

        $route = new Route(
            strtoupper($method),
            $fullPath,
            $handler,
            $middlewares,
            function (string $name, Route $route) use ($namePrefix): void {
                $fullName = $namePrefix . $name;
                if (isset($this->namedRoutes[$fullName])) {
                    throw new RouterException("Дублирующееся имя маршрута: '{$fullName}'");
                }
                $this->namedRoutes[$fullName] = $route;
            }
        );

        $this->routes[] = $route;
        return $route;
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = [
            'prefix'     => (string) ($attributes['prefix'] ?? ''),
            'namePrefix' => (string) ($attributes['namePrefix'] ?? ''),
            'middleware' => (array) ($attributes['middleware'] ?? []),
        ];

        try {
            $callback($this);
        } finally {
            array_pop($this->groupStack);
        }
    }

    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new RouterException("Именованный маршрут '{$name}' не найден");
        }

        return $this->namedRoutes[$name]->buildUri($params);
    }

    public function dispatch(RequestInterface $request): ResponseInterface
    {
        $method = $request->method();
        $uri = $request->uri();

        foreach ($this->routes as $route) {
            $params = [];
            if ($route->matches($method, $uri, $params)) {
                return $this->runPipeline($route, $request, $params);
            }
        }

        throw new RouteNotFoundException("Маршрут [{$method}] {$uri} не найден");
    }

    private function runPipeline(Route $route, RequestInterface $request, array $params): ResponseInterface
    {
        $coreHandler = function (RequestInterface $req) use ($route, $params): ResponseInterface {
            $arguments = array_merge($params, [
                'request' => $req,
                RequestInterface::class => $req,
            ]);

            $result = $this->invoker->call($route->getHandler(), $arguments);

            if (!$result instanceof ResponseInterface) {
                throw new RouterException('Обработчик маршрута должен возвращать объект ResponseInterface');
            }

            return $result;
        };

        $pipeline = array_reduce(
            array_reverse($route->getMiddlewares()),
            function (callable $next, string $middlewareClass): callable {
                return function (RequestInterface $req) use ($middlewareClass, $next): ResponseInterface {
                    $middleware = $this->container->get($middlewareClass);
                    if (!$middleware instanceof MiddlewareInterface) {
                        throw new RouterException("Класс {$middlewareClass} должен реализовывать MiddlewareInterface");
                    }
                    return $middleware->process($req, $next);
                };
            },
            $coreHandler
        );

        return $pipeline($request);
    }

    private function getCurrentPrefix(): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            if ($group['prefix'] !== '') {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }
        return $prefix;
    }

    private function getCurrentNamePrefix(): string
    {
        $namePrefix = '';
        foreach ($this->groupStack as $group) {
            $namePrefix .= $group['namePrefix'];
        }
        return $namePrefix;
    }

    private function getCurrentMiddlewares(): array
    {
        $middlewares = [];
        foreach ($this->groupStack as $group) {
            $middlewares = array_merge($middlewares, $group['middleware']);
        }
        return $middlewares;
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');
        return $normalized === '' ? '/' : $normalized;
    }
	
	    /**
     * Экспортирует все маршруты и карту именованных маршрутов.
     * Возвращает null, если среди обработчиков есть замыкания (Closure).
     *
     * @return array{routes: array<int, array<string, mixed>>, named: array<string, int>}|null
     */
    public function exportCompiled(): ?array
    {
        $exportedRoutes = [];
        $routeIndexMap = [];

        foreach ($this->routes as $index => $route) {
            if (!is_array($route->getHandler())) {
                return null;
            }
            $exportedRoutes[$index] = $route->toArray();
            $routeIndexMap[spl_object_id($route)] = $index;
        }

        $namedMap = [];
        foreach ($this->namedRoutes as $fullName => $route) {
            $objId = spl_object_id($route);
            if (isset($routeIndexMap[$objId])) {
                $namedMap[$fullName] = $routeIndexMap[$objId];
            }
        }

        return [
            'routes' => $exportedRoutes,
            'named'  => $namedMap,
        ];
    }

    /**
     * Загружает скомпилированные маршруты из кэша.
     *
     * @param array{routes: array<int, array<string, mixed>>, named: array<string, int>} $compiled
     */
    public function loadCompiled(array $compiled): void
    {
        $this->routes = [];
        $this->namedRoutes = [];

        foreach ($compiled['routes'] as $index => $routeData) {
            $this->routes[$index] = Route::fromArray($routeData);
        }

        foreach ($compiled['named'] as $fullName => $index) {
            if (isset($this->routes[$index])) {
                $this->namedRoutes[$fullName] = $this->routes[$index];
            }
        }
    }

}
