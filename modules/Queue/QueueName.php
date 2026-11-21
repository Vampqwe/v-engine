<?php

declare(strict_types=1);

namespace Modules\Queue;

final class QueueName
{
    public static function validate(string $queue): void
    {
        if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}\z/', $queue) !== 1) {
            throw new QueueException('Имя очереди должно содержать 1–128 букв, цифр, точек, дефисов или подчёркиваний');
        }
    }
}
