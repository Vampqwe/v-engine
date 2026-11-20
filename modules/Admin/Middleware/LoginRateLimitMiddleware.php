<?php

declare(strict_types=1);

namespace Modules\Admin\Middleware;

use App\Middleware\RateLimitMiddleware;
use VEngine\System\Http\RequestInterface;

final class LoginRateLimitMiddleware extends RateLimitMiddleware
{
    public const BUCKET = 'admin_login';

    protected function bucket(RequestInterface $request): string
    {
        return self::BUCKET;
    }
}
