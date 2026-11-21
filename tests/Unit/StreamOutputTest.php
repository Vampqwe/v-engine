<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\System\Console\StreamOutput;

final class StreamOutputTest extends TestCase
{
    public function testTableAlignsMultibyteTextByTerminalWidth(): void
    {
        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);

        $output = new StreamOutput($stream, $stream);
        $output->table(
            ['Команда', 'Описание'],
            [['cache:clear', 'Очистить кэш приложения.']],
        );

        rewind($stream);
        $lines = explode(PHP_EOL, trim(stream_get_contents($stream)));
        fclose($stream);

        self::assertCount(1, array_unique(array_map(
            static fn(string $line): int => mb_strwidth($line, 'UTF-8'),
            $lines,
        )));
    }
}
