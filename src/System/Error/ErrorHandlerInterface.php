<?php

declare(strict_types=1);

namespace VEngine\System\Error;

interface ErrorHandlerInterface
{
    public function register(): void;
}
