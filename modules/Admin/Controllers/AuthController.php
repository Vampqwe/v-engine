<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use App\Controllers\AbstractController;
use Modules\Admin\Services\AdminUserService;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\RateLimit\RateLimiterException;
use VEngine\System\RateLimit\RateLimiterInterface;
use VEngine\System\Router\RouterInterface;
use VEngine\System\Session\SessionInterface;
use VEngine\System\Validator\ValidatorInterface;
use VEngine\System\View\ViewInterface;

final class AuthController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(
        RouterInterface $router,
        ViewInterface $view,
        private readonly SessionInterface $session,
        private readonly ValidatorInterface $validator,
        private readonly AdminUserService $adminUsers,
        private readonly RateLimiterInterface $rateLimiter,
        LoggerInterface $logger,
    ) {
        parent::__construct($router, $view);
        $this->logger = $logger->forModule('Admin');
    }

    public function showLogin(): ResponseInterface
    {
        if ($this->session->has('admin_user_id')) {
            return $this->redirectToRoute('admin.dashboard');
        }

        return $this->view('@Admin/login', [
            'title' => 'Вход в панель управления',
        ], '@Admin/layout');
    }

    public function login(RequestInterface $request): ResponseInterface
    {
        $data = [
            'email'    => trim((string) $request->input('email', '')),
            'password' => (string) $request->input('password', ''),
        ];

        $isValid = $this->validator->validate($data, [
            'email'    => 'required|email',
            'password' => 'required|string|min:4',
        ], [
            'email.required'    => 'Введите email.',
            'email.email'       => 'Некорректный формат email.',
            'password.required' => 'Введите пароль.',
            'password.min'      => 'Пароль слишком короткий.',
        ]);

        if (!$isValid) {
            $firstField = array_key_first($this->validator->errors());
            $errorMessage = $firstField !== null
                ? ($this->validator->firstError($firstField) ?? 'Ошибка валидации')
                : 'Ошибка валидации';

            $this->session->flash('error', $errorMessage);
            $this->session->flash('old_email', $data['email']);
            return $this->redirectToRoute('admin.login');
        }

        $admin = $this->adminUsers->authenticate($data['email'], $data['password']);

        if ($admin === null) {
            $this->logger->error('Неудачная попытка входа в админ-панель', [
                'email' => $data['email'],
                'ip'    => $request->ip(),
            ]);
            $this->session->flash('error', 'Неверный email или пароль.');
            $this->session->flash('old_email', $data['email']);
            return $this->redirectToRoute('admin.login');
        }

        // Сбрасываем счетчик неудачных попыток для этого IP
        try {
            $this->rateLimiter->clear('admin_login', $request->ip());
        } catch (RateLimiterException $e) {
            $this->logger->error('Не удалось сбросить лимит попыток входа', [
                'ip'    => $request->ip(),
                'error' => $e->getMessage(),
            ]);
        }

        // Защита от фиксации сессии (Session Fixation)
        $this->session->regenerate(true);
        $this->session->set('admin_user_id', (int) $admin['id']);
        $this->session->flash('success', 'Добро пожаловать в панель управления!');

        $this->logger->info('Успешный вход администратора', [
            'admin_id' => $admin['id'],
            'email'    => $admin['email'],
        ]);

        return $this->redirectToRoute('admin.dashboard');
    }

    public function logout(): ResponseInterface
    {
        $this->session->remove('admin_user_id');
        $this->session->regenerate(true);
        $this->session->flash('success', 'Вы успешно вышли из системы.');

        return $this->redirectToRoute('admin.login');
    }
}
