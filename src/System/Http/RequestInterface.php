<?php

declare(strict_types=1);

namespace VEngine\System\Http;

interface RequestInterface
{
    public function method(): string;
    public function uri(): string;
    public function query(string $key, mixed $default = null): mixed;
    public function input(string $key, mixed $default = null): mixed;
    public function all(): array;
    public function header(string $name, mixed $default = null): mixed;
    public function isAjax(): bool;
    public function withAttribute(string $name, mixed $value): self;
    public function attribute(string $name, mixed $default = null): mixed;
    public function ip(): string;
}
