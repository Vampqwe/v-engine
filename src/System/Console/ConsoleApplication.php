<?php

declare(strict_types=1);

namespace VEngine\System\Console;

use Throwable;

final class ConsoleApplication implements ConsoleApplicationInterface
{
    /** @var array<string, Command> */
    private array $commands = [];

    public function register(Command $command): void
    {
        if (isset($this->commands[$command->name()])) {
            throw new \InvalidArgumentException("Команда уже зарегистрирована: {$command->name()}");
        }

        $this->commands[$command->name()] = $command;
    }

    public function execute(string $name, InputInterface $input, OutputInterface $output): int
    {
        $command = $this->commands[$name] ?? null;
        if ($command === null) {
            throw new \InvalidArgumentException("Неизвестная команда: {$name}");
        }

        return $command->handle($input, $output);
    }

    public function commands(): array
    {
        return array_values($this->commands);
    }

    public function run(array $argv, OutputInterface $output): int
    {
        $name = $argv[1] ?? 'list';
        $tokens = array_slice($argv, 2);

        if ($name === 'list') {
            $this->writeList($output);
            return 0;
        }
        if ($name === 'help') {
            $helpName = $tokens[0] ?? null;
            if ($helpName === null) {
                $this->writeList($output);
                return 0;
            }
            return $this->writeHelp($helpName, $output) ? 0 : 2;
        }

        $command = $this->commands[$name] ?? null;
        if ($command === null) {
            $output->error("Неизвестная команда: {$name}");
            $this->writeList($output);
            return 2;
        }

        try {
            if (in_array('--help', $tokens, true)) {
                return $this->writeHelp($name, $output) ? 0 : 2;
            }
            $input = ArrayInput::fromTokens($tokens, $command, $output);
            return $command->handle($input, $output);
        } catch (\InvalidArgumentException $exception) {
            $output->error($exception->getMessage());
            return 2;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }

    private function writeList(OutputInterface $output): void
    {
        $rows = [];
        foreach ($this->commands() as $command) {
            $rows[] = [$command->name(), $command->description()];
        }
        $output->writeln('Доступные команды:');
        $output->table(['Команда', 'Описание'], $rows);
        $output->writeln('Использование: php bin/console.php <команда> [аргументы] [опции]');
    }

    private function writeHelp(string $name, OutputInterface $output): bool
    {
        $command = $this->commands[$name] ?? null;
        if ($command === null) {
            $output->error("Неизвестная команда: {$name}");
            return false;
        }

        $usage = 'php bin/console.php ' . $name;
        foreach ($command->arguments() as $argument => $definition) {
            $usage .= ($definition['required'] ?? false) ? " <{$argument}>" : " [{$argument}]";
        }
        if ($command->options() !== []) {
            $usage .= ' [опции]';
        }

        $output->writeln($command->description());
        $output->writeln("Использование: {$usage}");
        foreach ($command->arguments() as $argument => $definition) {
            $output->writeln(sprintf(
                '  %-18s %s',
                $argument,
                $definition['description'] ?? '',
            ));
        }
        foreach ($command->options() as $option => $definition) {
            $output->writeln(sprintf(
                '  --%-16s %s',
                $option,
                $definition['description'] ?? '',
            ));
        }

        return true;
    }
}
