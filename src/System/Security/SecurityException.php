<?php

declare(strict_types=1);

namespace VEngine\System\Security;

use Throwable;
use VEngine\System\Http\HttpException;

class SecurityException extends HttpException
{
    public function __construct(string $message = 'Ошибка безопасности запроса', int $statusCode = 403, ?Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
    }
}
