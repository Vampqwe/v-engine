<?php

declare(strict_types=1);

namespace VEngine\System\File;

final class File implements FileInterface
{
    public function exists(string $path): bool
    {
        return file_exists($path) && is_file($path);
    }

    public function read(string $path): string
    {
        if (!$this->exists($path)) {
            throw new FileException("Файл не найден: {$path}");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new FileException("Ошибка чтения файла: {$path}");
        }
        return $content;
    }

    public function write(string $path, string $content, bool $append = false): bool
    {
        $this->ensureDirectory(dirname($path));
        $flags = $append ? FILE_APPEND | LOCK_EX : LOCK_EX;
        if (file_put_contents($path, $content, $flags) === false) {
            throw new FileException("Ошибка записи в файл: {$path}");
        }
        return true;
    }

    public function ensureDirectory(string $dirPath): void
    {
        if (!is_dir($dirPath) && !mkdir($dirPath, 0o755, true) && !is_dir($dirPath)) {
            throw new FileException("Не удалось создать директорию: {$dirPath}");
        }
    }
}
