<?php

declare(strict_types=1);

namespace VEngine\System\Http;

use Throwable;
use VEngine\System\Exception\BaseException;

class HttpException extends BaseException
{
    public function __construct(
        string $message = '',
        private readonly int $statusCode = 500,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
