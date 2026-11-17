<?php

declare(strict_types=1);

namespace VEngine\System\Map;

interface MapInterface
{
    public function get(array $array, string $key, mixed $default = null): mixed;
    public function has(array $array, string $key): bool;
    public function set(array &$array, string $key, mixed $value): void;
}
