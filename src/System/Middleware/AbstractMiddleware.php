<?php

declare(strict_types=1);

namespace VEngine\System\Middleware;

use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\Response;
use VEngine\System\Http\ResponseInterface;

abstract class AbstractMiddleware implements MiddlewareInterface
{
    abstract public function process(RequestInterface $request, callable $next): ResponseInterface;

    protected function jsonError(string $message, int $status = 403): ResponseInterface
    {
        return Response::json([
            'error'  => true,
            'status' => $status,
            'message' => $message,
        ], $status);
    }

    protected function redirect(string $url, int $status = 302): ResponseInterface
    {
        return Response::redirect($url, $status);
    }

    protected function isJsonRequest(RequestInterface $request): bool
    {
        $accept = (string) $request->header('accept', '');
        return $request->isAjax() || str_contains($accept, 'application/json');
    }
}
