<?php

declare(strict_types=1);

namespace VEngine\System\Http;

interface ResponseInterface
{
    public function withStatus(int $code): self;
    public function withHeader(string $name, string $value): self;
    public function send(): void;
}
