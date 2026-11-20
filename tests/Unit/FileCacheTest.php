<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\System\Cache\FileCache;
use VEngine\System\File\File;
use VEngine\System\File\FileException;
use VEngine\System\File\FileInterface;
use VEngine\System\TimeDate\TimeDate;

final class FileCacheTest extends TestCase
{
    private string $directory;
    private RecordingFile $file;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/vengine-file-cache-' . bin2hex(random_bytes(6));
        $this->file = new RecordingFile(new File());
        $this->cache = new FileCache($this->directory, $this->file, new TimeDate());
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testWritesToTemporaryFileBeforeReplacingTheCacheEntry(): void
    {
        self::assertTrue($this->cache->set('shared-key', 'first'));
        self::assertTrue($this->cache->set('shared-key', 'complete replacement'));

        $target = $this->directory . '/' . md5('shared-key') . '.cache';
        self::assertNotSame($target, $this->file->writtenPaths[0]);
        self::assertNotSame($target, $this->file->writtenPaths[1]);
        self::assertSame($this->directory, dirname($this->file->writtenPaths[0]));
        self::assertSame('complete replacement', $this->cache->get('shared-key'));
        self::assertSame([], glob($target . '.*') ?: []);
    }

    public function testConcurrentDeletionDuringReadIsTreatedAsCacheMiss(): void
    {
        $file = new class implements FileInterface {
            private bool $firstExistsCheck = true;

            public function exists(string $path): bool
            {
                if ($this->firstExistsCheck) {
                    $this->firstExistsCheck = false;
                    return true;
                }

                return false;
            }

            public function read(string $path): string
            {
                throw new FileException('Cache entry was deleted');
            }

            public function write(string $path, string $content, bool $append = false): bool
            {
                return false;
            }

            public function ensureDirectory(string $dirPath): void {}
        };
        $cache = new FileCache($this->directory, $file, new TimeDate());

        self::assertSame('fallback', $cache->get('concurrently-deleted', 'fallback'));
    }

    public function testNonPositiveTtlDeletesInsteadOfWriting(): void
    {
        self::assertTrue($this->cache->set('expired', 'value', 60));
        self::assertTrue($this->cache->set('expired', 'replacement', 0));
        self::assertSame('default', $this->cache->get('expired', 'default'));
    }
}

final class RecordingFile implements FileInterface
{
    /** @var string[] */
    public array $writtenPaths = [];

    public function __construct(private readonly FileInterface $file) {}

    public function exists(string $path): bool
    {
        return $this->file->exists($path);
    }

    public function read(string $path): string
    {
        return $this->file->read($path);
    }

    public function write(string $path, string $content, bool $append = false): bool
    {
        $this->writtenPaths[] = $path;
        return $this->file->write($path, $content, $append);
    }

    public function ensureDirectory(string $dirPath): void
    {
        $this->file->ensureDirectory($dirPath);
    }
}
