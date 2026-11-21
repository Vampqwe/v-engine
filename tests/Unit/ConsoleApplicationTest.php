<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use VEngine\System\Console\ArrayInput;
use VEngine\System\Console\BufferedOutput;
use VEngine\System\Console\Command;
use VEngine\System\Console\ConsoleApplication;
use VEngine\System\Console\InputInterface;
use VEngine\System\Console\OutputInterface;

final class ConsoleApplicationTest extends TestCase
{
    public function testDispatchesArgumentsAndOptionsWithoutWritingToProcessOutput(): void
    {
        $application = new ConsoleApplication();
        $application->register(new ConsoleTestCommand());
        $output = new BufferedOutput();

        $exitCode = $application->run(
            ['console', 'test:echo', 'hello', '--upper'],
            $output,
        );

        self::assertSame(0, $exitCode);
        self::assertSame('HELLO' . PHP_EOL, $output->content());
    }

    public function testListsAndDescribesRegisteredCommands(): void
    {
        $application = new ConsoleApplication();
        $application->register(new ConsoleTestCommand());
        $listOutput = new BufferedOutput();
        $helpOutput = new BufferedOutput();

        self::assertSame(0, $application->run(['console', 'list'], $listOutput));
        self::assertStringContainsString('test:echo', $listOutput->content());
        self::assertSame(0, $application->run(['console', 'test:echo', '--help'], $helpOutput));
        self::assertStringContainsString('<value>', $helpOutput->content());
    }

    public function testRejectsUnknownOptionsAndDuplicateCommands(): void
    {
        $application = new ConsoleApplication();
        $application->register(new ConsoleTestCommand());
        $output = new BufferedOutput();

        self::assertSame(2, $application->run(
            ['console', 'test:echo', 'hello', '--unknown'],
            $output,
        ));
        self::assertStringContainsString('Неизвестная опция', $output->content());

        $this->expectException(InvalidArgumentException::class);
        $application->register(new ConsoleTestCommand());
    }

    public function testUnknownCommandAndHelpReturnNonZeroExitCodes(): void
    {
        $application = new ConsoleApplication();
        $application->register(new ConsoleTestCommand());

        self::assertSame(2, $application->run(['console', 'missing:command'], new BufferedOutput()));
        self::assertSame(2, $application->run(['console', 'help', 'missing:command'], new BufferedOutput()));
    }
}

final class ConsoleTestCommand extends Command
{
    public function __construct()
    {
        parent::__construct(
            'test:echo',
            'Вывести переданное значение.',
            arguments: ['value' => ['required' => true]],
            options: ['upper' => []],
        );
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $value = $input->argument('value', '') ?? '';
        $output->writeln($input->option('upper') === true ? strtoupper($value) : $value);
        return 0;
    }
}
