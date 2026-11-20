<?php

declare(strict_types=1);

namespace Modules\Admin\Middleware;

use Modules\Admin\Services\AdminUserService;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Middleware\AbstractMiddleware;
use VEngine\System\Router\RouterInterface;
use VEngine\System\Session\SessionInterface;

final class AdminAuthMiddleware extends AbstractMiddleware
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly SessionInterface $session,
        private readonly RouterInterface $router,
        private readonly AdminUserService $adminUsers,
        LoggerInterface $logger,
    ) {
        $this->logger = $logger->forModule('Admin');
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $adminId = $this->session->get('admin_user_id');
        $this->session->close();

        if (!is_int($adminId) && !ctype_digit((string) $adminId)) {
            return $this->denyAccess($request);
        }

        $admin = $this->adminUsers->findById((int) $adminId);
        if ($admin === null) {
            $this->session->remove('admin_user_id');
            return $this->denyAccess($request);
        }

        $request = $request->withAttribute('admin.user', $admin);

        return $next($request);
    }

    private function denyAccess(RequestInterface $request): ResponseInterface
    {
        $this->logger->info('Попытка неавторизованного доступа в админ-панель', [
            'uri' => $request->uri(),
        ]);

        if ($this->isJsonRequest($request)) {
            return $this->jsonError('Требуется авторизация администратора', 401);
        }

        $this->session->flash('error', 'Пожалуйста, войдите в панель управления.');
        return $this->redirect($this->router->route('admin.login'));
    }
}
