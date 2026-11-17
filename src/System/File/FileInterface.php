<?php

declare(strict_types=1);

namespace VEngine\System\File;

interface FileInterface
{
    public function exists(string $path): bool;
    public function read(string $path): string;
    public function write(string $path, string $content, bool $append = false): bool;
    public function ensureDirectory(string $dirPath): void;
}
