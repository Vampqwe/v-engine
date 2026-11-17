<?php

declare(strict_types=1);

namespace VEngine\System\Router;

final class Route
{
    private ?string $name = null;
    private array $middlewares = [];
    private string $regex;
    private array $paramNames = [];

    /**
     * @param callable|array $handler
     * @param callable|null $onNamedCallback
     */
    public function __construct(
        private readonly string $method,
        private readonly string $pattern,
        private readonly mixed $handler,
        array $middlewares = [],
        private readonly ?\Closure $onNamedCallback = null
    ) {
        $this->middlewares = $middlewares;
        $this->compilePattern();
    }

    public function name(string $name): self
    {
        $this->name = $name;
        if ($this->onNamedCallback !== null) {
            ($this->onNamedCallback)($name, $this);
        }
        return $this;
    }

    public function middleware(string|array $middleware): self
    {
        $items = is_array($middleware) ? $middleware : [$middleware];
        $this->middlewares = array_merge($this->middlewares, $items);
        return $this;
    }

    public function matches(string $method, string $uri, array &$params = []): bool
    {
        if ($this->method !== $method) {
            return false;
        }

        if (!preg_match($this->regex, $uri, $matches)) {
            return false;
        }

        $params = [];
        foreach ($this->paramNames as $name) {
            if (isset($matches[$name])) {
                $params[$name] = $matches[$name];
            }
        }

        return true;
    }

    public function buildUri(array $params = []): string
    {
        $uri = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^}]+)?\}/', function (array $m) use ($params): string {
            $key = $m[1];
            if (!array_key_exists($key, $params)) {
                throw new RouterException("Отсутствует обязательный параметр '{$key}' для маршрута '{$this->name}'");
            }
            return rawurlencode((string) $params[$key]);
        }, $this->pattern);

        return $uri ?? $this->pattern;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    private function compilePattern(): void
    {
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/', function (array $m): string {
            $this->paramNames[] = $m[1];
            $constraint = $m[2] ?? '[^/]+';
            return "(?P<{$m[1]}>{$constraint})";
        }, $this->pattern);

        $this->regex = '#^' . $regex . '$#u';
    }
}
