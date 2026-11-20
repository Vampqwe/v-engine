<?php

declare(strict_types=1);

namespace App\Middleware;

use VEngine\System\Config\ConfigInterface;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\Response;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Middleware\AbstractMiddleware;

final class AuthMiddleware extends AbstractMiddleware
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly ConfigInterface $config,
        LoggerInterface $logger,
    ) {
        $this->logger = $logger->forModule('Auth');
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $expectedToken = $this->config->get('auth', 'api_token', '');
        if (!is_string($expectedToken) || $expectedToken === '') {
            $this->logger->error('API authentication is unavailable: API_TOKEN is not configured');
            return $this->isApiRequest($request)
                ? $this->jsonError('Сервис авторизации временно недоступен', 503)
                : Response::html('Сервис авторизации временно недоступен', 503);
        }

        $providedToken = $this->bearerToken($request->header('authorization'));
        if ($providedToken === null || !hash_equals($expectedToken, $providedToken)) {
            $this->logger->info('Отказ в доступе: отсутствует токен', [
                'uri' => $request->uri(),
            ]);

            if ($this->isApiRequest($request) || $this->isJsonRequest($request)) {
                return $this->jsonError('Требуется действительный Bearer-токен', 401)
                    ->withHeader('WWW-Authenticate', 'Bearer');
            }

            return $this->redirect('/');
        }

        return $next($request);
    }

    private function bearerToken(mixed $authorization): ?string
    {
        if (!is_string($authorization)) {
            return null;
        }

        if (!preg_match('/\ABearer[ \t]+([A-Za-z0-9\-._~+\/]+=*)\z/i', trim($authorization), $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function isApiRequest(RequestInterface $request): bool
    {
        return str_starts_with($request->uri(), '/api/');
    }
}
