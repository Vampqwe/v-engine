<?php

declare(strict_types=1);

namespace VEngine\System\Console;

interface ConsoleApplicationInterface
{
    public function register(Command $command): void;

    public function execute(string $name, InputInterface $input, OutputInterface $output): int;

    /**
     * @return array<int, Command>
     */
    public function commands(): array;

    public function run(array $argv, OutputInterface $output): int;
}
