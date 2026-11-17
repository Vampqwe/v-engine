<?php

declare(strict_types=1);

namespace VEngine\System\Router;

use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;

interface RouterInterface
{
    public function get(string $path, callable|array $handler): Route;
    public function post(string $path, callable|array $handler): Route;
    public function put(string $path, callable|array $handler): Route;
    public function delete(string $path, callable|array $handler): Route;
    public function add(string $method, string $path, callable|array $handler): Route;
    public function group(array $attributes, callable $callback): void;
    public function route(string $name, array $params = []): string;
    public function dispatch(RequestInterface $request): ResponseInterface;
}
