<?php

declare(strict_types=1);

namespace VEngine\System\Redis;

use InvalidArgumentException;

final class RedisNamespace
{
    public static function forSite(string $prefix, string $site): string
    {
        if ($site === '') {
            throw new InvalidArgumentException('Имя сайта для Redis namespace не может быть пустым');
        }

        return $prefix . $site . ':';
    }
}
