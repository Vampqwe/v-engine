<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\System\Router\RouteCacheFingerprint;

final class RouteCacheFingerprintTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/vengine-route-fingerprint-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0o700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testFingerprintChangesWhenRouteContentsOrFileListChanges(): void
    {
        $firstFile = $this->directory . '/web.php';
        file_put_contents($firstFile, '<?php return static fn() => null;');
        $initialFingerprint = RouteCacheFingerprint::fromFiles([$firstFile]);

        file_put_contents($firstFile, '<?php return static fn() => "changed";');
        $changedContentFingerprint = RouteCacheFingerprint::fromFiles([$firstFile]);

        $secondFile = $this->directory . '/api.php';
        file_put_contents($secondFile, '<?php return static fn() => null;');
        $changedFileListFingerprint = RouteCacheFingerprint::fromFiles([$firstFile, $secondFile]);

        self::assertNotSame($initialFingerprint, $changedContentFingerprint);
        self::assertNotSame($changedContentFingerprint, $changedFileListFingerprint);
    }
}
