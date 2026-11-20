<?php

declare(strict_types=1);

namespace VEngine\System\Error;

use Throwable;

interface ErrorRendererInterface
{
    public function render(Throwable $exception, bool $isDebug, int $statusCode = 500): string;
}
