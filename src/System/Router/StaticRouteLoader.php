<?php

declare(strict_types=1);

namespace VEngine\System\Router;

use JsonException;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;

final class StaticRouteLoader
{
    public function load(
        RouterInterface $router,
        StaticPageController $controller,
        string $routesFile,
        string $aliasesFile,
    ): void {
        $routes = $this->readMap($routesFile, 'routes');
        $aliases = $this->readMap($aliasesFile, 'route aliases');

        foreach ($routes as $path => $page) {
            $this->validatePath($path, 'route');
            $this->validatePage($page);
        }

        foreach ($aliases as $alias => $target) {
            $this->validatePath($alias, 'alias');
            $this->validatePath($target, 'alias target');
            if (isset($routes[$alias])) {
                throw new RouterException("Static alias conflicts with a route: {$alias}");
            }
            if (!isset($routes[$target])) {
                throw new RouterException("Static alias target is not a declared route: {$target}");
            }
        }

        foreach ($routes as $path => $page) {
            $this->registerPage($router, $controller, $path, $page);
        }
        foreach ($aliases as $alias => $target) {
            $this->registerPage($router, $controller, $alias, $routes[$target]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function readMap(string $file, string $description): array
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new RouterException("Файл {$description} не найден или недоступен для чтения: {$file}");
        }
        $json = file_get_contents($file);
        if ($json === false) {
            throw new RouterException("Не удалось прочитать {$description}: {$file}");
        }

        try {
            $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RouterException("Некорректный JSON ({$description}): {$file}", 0, $exception);
        }

        if (!$data instanceof \stdClass) {
            throw new RouterException("Файл {$description} должен содержать JSON-объект: {$file}");
        }

        $map = [];
        foreach ($data as $path => $value) {
            if (!is_string($value) || $value === '') {
                throw new RouterException("Значения {$description} должны быть непустыми строками: {$file}");
            }
            $map[$path] = $value;
        }

        return $map;
    }

    private function validatePath(string $path, string $kind): void
    {
        if (
            $path === ''
            || $path[0] !== '/'
            || str_contains($path, '\\')
            || str_contains($path, '?')
            || str_contains($path, '#')
            || str_contains($path, '//')
            || ($path !== '/' && str_ends_with($path, '/'))
            || preg_match('/[\x00-\x20]/', $path) === 1
        ) {
            throw new RouterException("Некорректный static {$kind} path: {$path}");
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new RouterException("Static {$kind} path не может содержать dot-сегменты: {$path}");
            }
        }
    }

    private function validatePage(string $page): void
    {
        if (
            str_contains($page, '\\')
            || str_starts_with($page, '/')
            || preg_match('/\A[A-Za-z0-9._\/-]+\.html\z/', $page) !== 1
        ) {
            throw new RouterException("Имя страницы должно быть относительным HTML-путём внутри pages/: {$page}");
        }

        foreach (explode('/', $page) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RouterException("Некорректный путь страницы: {$page}");
            }
        }
    }

    private function registerPage(
        RouterInterface $router,
        StaticPageController $controller,
        string $path,
        string $page,
    ): void {
        $handler = static fn(RequestInterface $request): ResponseInterface => $controller->show($request, $page);
        $router->add('GET', $path, $handler);
        $router->add('HEAD', $path, $handler);
    }
}
