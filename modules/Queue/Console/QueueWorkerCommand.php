<?php

declare(strict_types=1);

namespace Modules\Queue\Console;

use Modules\Queue\QueueException;
use Modules\Queue\Worker;
use VEngine\System\Console\Command;
use VEngine\System\Console\InputInterface;
use VEngine\System\Console\OutputInterface;

final class QueueWorkerCommand extends Command
{
    private bool $shutdownRequested = false;
    private bool $previousAsyncSignals = false;

    /** @var array<int, mixed> */
    private array $previousSignalHandlers = [];

    public function __construct(private readonly Worker $worker)
    {
        parent::__construct(
            'queue:work',
            'Обрабатывать задания очереди до таймаута или сигнала остановки.',
            options: [
                'queue'   => ['value' => true, 'description' => 'Имя очереди (по умолчанию default).'],
                'timeout' => ['value' => true, 'description' => 'Максимальное время жизни процесса в секундах; текущая задача завершается.'],
                'tries'   => ['value' => true, 'description' => 'Максимальное число попыток для каждого задания.'],
            ],
        );
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $this->shutdownRequested = false;
        $queue = $input->option('queue', 'default');
        if (!is_string($queue)) {
            throw new QueueException('Опция --queue должна содержать имя очереди');
        }

        $timeout = $this->positiveIntegerOption($input->option('timeout', '0'), 'timeout', true);
        $triesValue = $input->option('tries');
        $tries = $triesValue === null
            ? null
            : $this->positiveIntegerOption($triesValue, 'tries', false);

        $this->installSignalHandlers();
        try {
            $processed = $this->worker->run(
                $queue,
                $timeout,
                $tries,
                fn(): bool => $this->shutdownRequested,
            );
        } finally {
            $this->restoreSignalHandlers();
        }

        $output->writeln(sprintf(
            'Worker остановлен; обработано заданий: %d%s',
            $processed,
            $this->shutdownRequested ? ' (graceful shutdown)' : '',
        ));
        return 0;
    }

    private function positiveIntegerOption(string|bool|null $value, string $name, bool $allowZero): int
    {
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new QueueException("Опция --{$name} должна быть целым числом");
        }
        $number = (int) $value;
        if (($allowZero && $number < 0) || (!$allowZero && $number < 1)) {
            throw new QueueException("Некорректное значение опции --{$name}");
        }
        return $number;
    }

    private function installSignalHandlers(): void
    {
        if (!extension_loaded('pcntl') || !function_exists('pcntl_signal')) {
            return;
        }

        $this->previousAsyncSignals = pcntl_async_signals();
        $this->previousSignalHandlers[SIGTERM] = pcntl_signal_get_handler(SIGTERM);
        $this->previousSignalHandlers[SIGINT] = pcntl_signal_get_handler(SIGINT);
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn() => $this->shutdownRequested = true);
        pcntl_signal(SIGINT, fn() => $this->shutdownRequested = true);
    }

    private function restoreSignalHandlers(): void
    {
        if (!extension_loaded('pcntl') || !function_exists('pcntl_signal')) {
            return;
        }

        foreach ($this->previousSignalHandlers as $signal => $handler) {
            pcntl_signal($signal, $handler);
        }
        pcntl_async_signals($this->previousAsyncSignals);
        $this->previousSignalHandlers = [];
    }
}
