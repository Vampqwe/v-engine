<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use InvalidArgumentException;
use VEngine\System\Console\ArrayInput;
use VEngine\System\Console\BufferedOutput;
use VEngine\System\Console\ConsoleApplicationInterface;

final class AdminConsoleService
{
    private const WEB_COMMANDS = [
        'cache:clear',
        'rate-limit:gc',
    ];

    public function __construct(private readonly ConsoleApplicationInterface $console) {}

    /**
     * @return array<int, array{name: string, description: string}>
     */
    public function commands(): array
    {
        $allowed = array_fill_keys(self::WEB_COMMANDS, true);
        $commands = [];
        foreach ($this->console->commands() as $command) {
            if (isset($allowed[$command->name()])) {
                $commands[] = [
                    'name'        => $command->name(),
                    'description' => $command->description(),
                ];
            }
        }

        return $commands;
    }

    /**
     * @return array{exitCode: int, output: string}
     */
    public function execute(string $name): array
    {
        if (!in_array($name, self::WEB_COMMANDS, true)) {
            throw new InvalidArgumentException('Эта команда недоступна из веб-интерфейса');
        }

        $output = new BufferedOutput();
        $options = $name === 'rate-limit:gc' ? ['non-blocking' => true] : [];
        $exitCode = $this->console->execute($name, new ArrayInput(options: $options), $output);

        return [
            'exitCode' => $exitCode,
            'output'   => $output->content(),
        ];
    }
}
