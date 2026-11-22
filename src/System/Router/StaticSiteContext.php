<?php

declare(strict_types=1);

namespace VEngine\System\Router;

final readonly class StaticSiteContext
{
    /**
     * @param array<string, string> $routes
     * @param array<string, string> $aliases
     */
    public function __construct(
        public RouterInterface $router,
        public string $publicPath,
        public string $baseUrl,
        public array $routes,
        public array $aliases,
    ) {}
}
