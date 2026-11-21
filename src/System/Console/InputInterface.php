<?php

declare(strict_types=1);

namespace VEngine\System\Console;

interface InputInterface
{
    public function argument(string $name, ?string $default = null): ?string;

    public function option(string $name, string|bool|null $default = null): string|bool|null;

    public function ask(string $question, string $default = ''): string;

    public function askHidden(string $question): string;
}
