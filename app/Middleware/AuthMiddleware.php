<?php

declare(strict_types=1);

namespace App\Middleware;

use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Middleware\AbstractMiddleware;

final class AuthMiddleware extends AbstractMiddleware
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger->forModule('Auth');
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $token = $request->header('authorization');

        if ($token === null || $token === '') {
            $this->logger->info('Отказ в доступе: отсутствует токен', [
                'uri' => $request->uri(),
            ]);

            if ($this->isJsonRequest($request)) {
                return $this->jsonError('Требуется заголовок Authorization', 401);
            }

            return $this->redirect('/');
        }

        $request = $request->withAttribute('auth.token', $token);

        return $next($request);
    }
}
