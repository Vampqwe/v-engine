<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CreateAdminCommandTest extends TestCase
{
    public function testRejectsPasswordArgument(): void
    {
        $script = dirname(__DIR__, 2) . '/bin/create-admin.php';
        $process = proc_open(
            [PHP_BINARY, $script, 'admin@example.com', 'Admin', 'blocked'],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame('', $stdout);
        self::assertStringContainsString('пароль вводится отдельно', $stderr);
        self::assertSame(2, $exitCode);
    }
}
