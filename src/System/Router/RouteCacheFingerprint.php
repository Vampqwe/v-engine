<?php

declare(strict_types=1);

namespace VEngine\System\Router;

use RuntimeException;

final class RouteCacheFingerprint
{
    /**
     * @param array<int, string> $files
     */
    public static function fromFiles(array $files): string
    {
        sort($files);
        $context = hash_init('sha256');

        foreach ($files as $file) {
            $contentHash = hash_file('sha256', $file);
            if ($contentHash === false) {
                throw new RuntimeException("Не удалось вычислить контрольную сумму файла маршрутов: {$file}");
            }

            hash_update($context, $file . "\0" . $contentHash . "\0");
        }

        return hash_final($context);
    }
}
