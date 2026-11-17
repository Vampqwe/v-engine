<?php

declare(strict_types=1);

namespace VEngine\System\Middleware;

use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;

interface MiddlewareInterface
{
    public function process(RequestInterface $request, callable $next): ResponseInterface;
}
