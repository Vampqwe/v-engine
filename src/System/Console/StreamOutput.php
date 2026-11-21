<?php

declare(strict_types=1);

namespace VEngine\System\Console;

final class StreamOutput implements OutputInterface
{
    public function __construct(
        private readonly mixed $outputStream = null,
        private readonly mixed $errorStream = null,
    ) {}

    public function write(string $message): void
    {
        fwrite($this->outputStream ?? STDOUT, $message);
    }

    public function writeln(string $message = ''): void
    {
        $this->write($message . PHP_EOL);
    }

    public function error(string $message): void
    {
        fwrite($this->errorStream ?? STDERR, $message . PHP_EOL);
    }

    public function table(array $headers, array $rows): void
    {
        $widths = array_map(static fn(string $cell): int => mb_strwidth($cell, 'UTF-8'), $headers);
        foreach ($rows as $row) {
            foreach ($headers as $index => $_header) {
                $widths[$index] = max(
                    $widths[$index],
                    mb_strwidth($row[$index] ?? '', 'UTF-8'),
                );
            }
        }

        $separator = '+' . implode('+', array_map(
            static fn(int $width): string => str_repeat('-', $width + 2),
            $widths,
        )) . '+';
        $formatRow = static function (array $row) use ($widths): string {
            $cells = array_pad($row, count($widths), '');
            foreach ($widths as $index => $width) {
                $cellWidth = mb_strwidth($cells[$index], 'UTF-8');
                $cells[$index] .= str_repeat(' ', $width - $cellWidth);
            }

            return '| ' . implode(' | ', $cells) . ' |';
        };

        $this->writeln($separator);
        $this->writeln($formatRow($headers));
        $this->writeln($separator);
        foreach ($rows as $row) {
            $this->writeln($formatRow($row));
        }
        $this->writeln($separator);
    }
}
