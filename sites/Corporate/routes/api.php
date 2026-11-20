<?php

declare(strict_types=1);

use App\Controllers\ApiController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use VEngine\System\Router\RouterInterface;

return static function (RouterInterface $router): void {
    $router->group([
        'prefix'     => '/api',
        'namePrefix' => 'api.',
        'middleware' => [RateLimitMiddleware::class],
    ], static function (RouterInterface $router): void {
        $router->get('/health', [ApiController::class, 'health'])->name('health');

        // Защищенный маршрут с использованием AuthMiddleware
        $router->get('/profile', [ApiController::class, 'profile'])
            ->middleware(AuthMiddleware::class)
            ->name('profile');
    });
};
