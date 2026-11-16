<?php

declare(strict_types=1);

namespace VEngine\System\Config;

interface ConfigInterface
{
    public function env(string $key, mixed $default = null): mixed;
    public function get(string $file, string $key, mixed $default = null): mixed;
}
