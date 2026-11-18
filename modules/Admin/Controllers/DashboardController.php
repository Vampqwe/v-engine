<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use App\Controllers\AbstractController;
use VEngine\System\Config\ConfigInterface;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Router\RouterInterface;
use VEngine\System\TimeDate\TimeDateInterface;
use VEngine\System\View\ViewInterface;

final class DashboardController extends AbstractController
{
    public function __construct(
        RouterInterface $router,
        ViewInterface $view,
        private readonly ConfigInterface $config,
        private readonly TimeDateInterface $timeDate
    ) {
        parent::__construct($router, $view);
    }

    public function index(RequestInterface $request): ResponseInterface
    {
        /** @var array<string, mixed> $adminUser */
        $adminUser = $request->attribute('admin.user', []);

        return $this->view('@Admin/dashboard', [
            'title'     => 'Панель управления',
            'adminUser' => $adminUser,
            'stats'     => [
                'php_version' => PHP_VERSION,
                'app_env'     => (string) $this->config->env('APP_ENV', 'dev'),
                'server_time' => $this->timeDate->now('d.m.Y H:i:s'),
            ],
        ], '@Admin/layout');
    }
}
