<?php

declare(strict_types=1);

namespace VEngine\System\Console;

interface OutputInterface
{
    public function write(string $message): void;

    public function writeln(string $message = ''): void;

    public function error(string $message): void;

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $rows
     */
    public function table(array $headers, array $rows): void;
}
