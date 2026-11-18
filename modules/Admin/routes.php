<?php

declare(strict_types=1);

use App\Middleware\CsrfMiddleware;
use Modules\Admin\Controllers\AuthController;
use Modules\Admin\Controllers\DashboardController;
use Modules\Admin\Middleware\AdminAuthMiddleware;
use Modules\Admin\Middleware\LoginRateLimitMiddleware;
use VEngine\System\Router\RouterInterface;

return static function (RouterInterface $router): void {
    $router->group(['prefix' => '/admin', 'namePrefix' => 'admin.'], static function (RouterInterface $router): void {
        // Публичные маршруты авторизации
        $router->get('/login', [AuthController::class, 'showLogin'])->name('login');
        $router->post('/login', [AuthController::class, 'login'])
            ->middleware([LoginRateLimitMiddleware::class, CsrfMiddleware::class])
            ->name('login.post');

        // Защищенные маршруты панели управления
        $router->group(['middleware' => [AdminAuthMiddleware::class]], static function (RouterInterface $router): void {
            $router->get('/', [DashboardController::class, 'index'])->name('dashboard');
            $router->post('/logout', [AuthController::class, 'logout'])
                ->middleware(CsrfMiddleware::class)
                ->name('logout');
        });
    });
};
