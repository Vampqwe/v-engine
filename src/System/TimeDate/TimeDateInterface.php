<?php

declare(strict_types=1);

namespace VEngine\System\TimeDate;

use DateTimeImmutable;

interface TimeDateInterface
{
    public function now(string $format = 'Y-m-d H:i:s'): string;
    public function timestamp(): int;
    public function immutable(?string $time = 'now'): DateTimeImmutable;
}
