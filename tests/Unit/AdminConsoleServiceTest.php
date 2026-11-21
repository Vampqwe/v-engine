<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\System\Console\ArrayInput;
use VEngine\System\Console\Command;
use VEngine\System\Console\ConsoleApplication;
use VEngine\System\Console\InputInterface;
use VEngine\System\Console\OutputInterface;
use Modules\Admin\Services\AdminConsoleService;

final class AdminConsoleServiceTest extends TestCase
{
    public function testOnlyAllowlistedCommandsAreExposedAndRateLimitGcIsNonBlocking(): void
    {
        $application = new ConsoleApplication();
        $application->register(new AdminConsoleTestCommand('cache:clear'));
        $application->register(new AdminConsoleTestCommand('rate-limit:gc'));
        $application->register(new AdminConsoleTestCommand('admin:create'));
        $service = new AdminConsoleService($application);

        self::assertSame(
            ['cache:clear', 'rate-limit:gc'],
            array_column($service->commands(), 'name'),
        );
        self::assertSame(
            ['exitCode' => 0, 'output' => 'non-blocking=true' . PHP_EOL],
            $service->execute('rate-limit:gc'),
        );
    }

    public function testRejectsCommandsNotEnabledForTheWebInterface(): void
    {
        $application = new ConsoleApplication();
        $application->register(new AdminConsoleTestCommand('admin:create'));
        $service = new AdminConsoleService($application);

        $this->expectException(\InvalidArgumentException::class);
        $service->execute('admin:create');
    }
}

final class AdminConsoleTestCommand extends Command
{
    public function __construct(string $name)
    {
        parent::__construct($name, 'Test command.', options: ['non-blocking' => []]);
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('non-blocking=' . ($input->option('non-blocking') === true ? 'true' : 'false'));
        return 0;
    }
}
