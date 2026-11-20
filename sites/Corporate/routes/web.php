<?php

declare(strict_types=1);

use Sites\Corporate\App\Controllers\HomeController;
use Sites\Corporate\App\Middleware\HomeSubmitRateLimitMiddleware;
use App\Middleware\CsrfMiddleware;
use VEngine\System\Router\RouterInterface;

return static function (RouterInterface $router): void {
    $router->get('/', [HomeController::class, 'index'])->name('home');
    $router->get('/hello/{name}', [HomeController::class, 'hello'])->name('hello');
    $router->post('/', [HomeController::class, 'submit'])
        ->middleware([HomeSubmitRateLimitMiddleware::class, CsrfMiddleware::class]);
};
