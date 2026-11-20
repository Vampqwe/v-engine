<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use App\Controllers\AbstractController;
use Modules\Admin\Services\EngineStatsService;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Router\RouterInterface;
use VEngine\System\View\ViewInterface;

final class DashboardController extends AbstractController
{
    public function __construct(
        RouterInterface $router,
        ViewInterface $view,
        private readonly EngineStatsService $engineStats,
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
            'stats'     => $this->engineStats->collect(),
        ], '@Admin/layout');
    }
}
