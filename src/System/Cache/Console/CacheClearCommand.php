<?php

declare(strict_types=1);

namespace VEngine\System\Cache\Console;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use VEngine\System\Console\Command;
use VEngine\System\Console\InputInterface;
use VEngine\System\Console\OutputInterface;

final class CacheClearCommand extends Command
{
    public function __construct(private readonly string $rootPath)
    {
        parent::__construct('cache:clear', 'Очистить кэш приложения и скомпилированные файлы.');
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $cacheDir = rtrim($this->rootPath, DIRECTORY_SEPARATOR) . '/storage/cache';
        $targets = [
            $cacheDir . '/sites',
            $cacheDir . '/routes.compiled.php',
            $cacheDir . '/di',
        ];

        foreach (glob($cacheDir . '/routes-*.compiled.php') ?: [] as $routeCache) {
            $targets[] = $routeCache;
        }

        foreach ($targets as $target) {
            if (is_link($target) || is_file($target)) {
                if (!unlink($target)) {
                    throw new RuntimeException("Не удалось удалить файл кэша: {$target}");
                }
                $output->writeln("[OK] Удален файл: {$target}");
            } elseif (is_dir($target)) {
                $this->removeDirectory($target);
                $output->writeln("[OK] Очищена директория: {$target}");
            }
        }

        $output->writeln('Системный кэш ядра успешно сброшен.');
        return 0;
    }

    private function removeDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if ($entry->isDir() && !$entry->isLink()) {
                if (!rmdir($path)) {
                    throw new RuntimeException("Не удалось удалить каталог кэша: {$path}");
                }
            } elseif (!unlink($path)) {
                throw new RuntimeException("Не удалось удалить файл кэша: {$path}");
            }
        }

        if (!rmdir($directory)) {
            throw new RuntimeException("Не удалось удалить каталог кэша: {$directory}");
        }
    }
}
