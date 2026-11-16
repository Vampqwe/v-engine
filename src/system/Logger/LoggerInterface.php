<?php

declare(strict_types=1);

namespace VEngine\System\Logger;

interface LoggerInterface
{
    public function forModule(string $moduleName): self;
    public function info(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
}
