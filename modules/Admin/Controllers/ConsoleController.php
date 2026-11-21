<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use App\Controllers\AbstractController;
use Modules\Admin\Services\AdminConsoleService;
use Throwable;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Router\RouterInterface;
use VEngine\System\View\ViewInterface;

final class ConsoleController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(
        RouterInterface $router,
        ViewInterface $view,
        private readonly AdminConsoleService $console,
        LoggerInterface $logger,
    ) {
        parent::__construct($router, $view);
        $this->logger = $logger->forModule('Admin');
    }

    public function index(RequestInterface $request): ResponseInterface
    {
        /** @var array<string, mixed> $adminUser */
        $adminUser = $request->attribute('admin.user', []);
        $result = $this->view->flash('cli.result');

        return $this->view('@Admin/console', [
            'title'     => 'CLI',
            'adminUser' => $adminUser,
            'commands'  => $this->console->commands(),
            'result'    => is_array($result) ? $result : null,
        ], '@Admin/layout');
    }

    public function execute(RequestInterface $request): ResponseInterface
    {
        $command = $request->input('command');
        if (!is_string($command)) {
            return $this->redirectToRoute('admin.cli');
        }

        try {
            $this->view->flash('cli.result', [
                'command' => $command,
                ...$this->console->execute($command),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Ошибка запуска команды из панели администратора', [
                'command'   => $command,
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
            ]);
            $this->view->flash('error', 'Не удалось выполнить команду.');
        }

        return $this->redirectToRoute('admin.cli');
    }
}
