<?php

declare(strict_types=1);

namespace VEngine\System\TimeDate;

use DateTimeImmutable;

final class TimeDate implements TimeDateInterface
{
    public function now(string $format = 'Y-m-d H:i:s'): string
    {
        return (new DateTimeImmutable())->format($format);
    }

    public function timestamp(): int
    {
        return time();
    }

    public function immutable(?string $time = 'now'): DateTimeImmutable
    {
        return new DateTimeImmutable($time ?? 'now');
    }
}
