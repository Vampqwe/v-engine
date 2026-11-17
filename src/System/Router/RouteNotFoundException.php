<?php

declare(strict_types=1);

namespace VEngine\System\Router;

use Throwable;
use VEngine\System\Http\HttpException;

class RouteNotFoundException extends HttpException
{
    public function __construct(string $message = 'Страница не найдена', ?Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
