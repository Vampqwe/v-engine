<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use VEngine\System\Router\RouterInterface;

return static function (RouterInterface $router): void {
    $router->get('/', [HomeController::class, 'index'])->name('home');
    $router->get('/hello/{name}', [HomeController::class, 'hello'])->name('hello');
};
