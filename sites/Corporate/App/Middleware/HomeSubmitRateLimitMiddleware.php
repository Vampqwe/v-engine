<?php

declare(strict_types=1);

namespace Sites\Corporate\App\Middleware;

use App\Middleware\RateLimitMiddleware;
use VEngine\System\Http\RequestInterface;

final class HomeSubmitRateLimitMiddleware extends RateLimitMiddleware
{
    protected function bucket(RequestInterface $request): string
    {
        return 'home_submit';
    }
}
