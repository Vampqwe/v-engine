<?php

declare(strict_types=1);

namespace App\Middleware;

use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Middleware\AbstractMiddleware;
use VEngine\System\Security\SecurityException;
use VEngine\System\Security\SecurityInterface;

final class CsrfMiddleware extends AbstractMiddleware
{
    private const UNSAFE_METHODS = ['POST', 'PUT', 'DELETE', 'PATCH'];

    private LoggerInterface $logger;

    public function __construct(
        private readonly SecurityInterface $security,
        LoggerInterface $logger
    ) {
        $this->logger = $logger->forModule('Security');
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        if (in_array($request->method(), self::UNSAFE_METHODS, true)) {
            $token = $request->input('_csrf_token') ?? $request->header('x-csrf-token');

            if (!$this->security->verifyCsrfToken(is_string($token) ? $token : null)) {
                $this->logger->error('Невалидный CSRF-токен', [
                    'method' => $request->method(),
                    'uri'    => $request->uri(),
                ]);

                if ($this->isJsonRequest($request)) {
                    return $this->jsonError('Недействительный CSRF-токен', 403);
                }

                throw new SecurityException('Ошибка проверки безопасности формы (CSRF). Обновите страницу и повторите попытку.', 403);
            }
        }

        return $next($request);
    }
}
