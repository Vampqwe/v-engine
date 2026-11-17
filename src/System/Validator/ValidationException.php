<?php

declare(strict_types=1);

namespace VEngine\System\Validator;

use Throwable;
use VEngine\System\Http\HttpException;

class ValidationException extends HttpException
{
    /**
     * @param array<string, string[]> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Ошибка валидации входных данных',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 422, $previous);
    }

    /**
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
