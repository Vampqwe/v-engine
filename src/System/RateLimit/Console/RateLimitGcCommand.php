<?php

declare(strict_types=1);

namespace VEngine\System\RateLimit\Console;

use VEngine\System\Console\Command;
use VEngine\System\Console\InputInterface;
use VEngine\System\Console\OutputInterface;
use VEngine\System\RateLimit\FileRateLimiter;

final class RateLimitGcCommand extends Command
{
    public function __construct(private readonly FileRateLimiter $rateLimiter)
    {
        parent::__construct(
            'rate-limit:gc',
            'Удалить устаревшие состояния файлового rate limiter.',
            options: [
                'non-blocking' => [
                    'description' => 'Не ждать освобождения блокировки (для вызова из web-интерфейса).',
                ],
            ],
        );
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $removed = $this->rateLimiter->collectExpiredStates($input->option('non-blocking') !== true);
        $output->writeln("Удалено устаревших состояний rate limiter: {$removed}");
        return 0;
    }
}
