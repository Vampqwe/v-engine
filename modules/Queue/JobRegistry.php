<?php

declare(strict_types=1);

namespace Modules\Queue;

use Psr\Container\ContainerInterface;

final class JobRegistry
{
    /** @var array<string, class-string<Job>> */
    private array $jobClasses = [];

    /**
     * @param array<int, string> $jobClasses
     */
    public function __construct(
        array $jobClasses,
        private readonly ContainerInterface $container,
    ) {
        foreach ($jobClasses as $jobClass) {
            if (!is_string($jobClass) || !is_subclass_of($jobClass, Job::class)) {
                throw new QueueException('В config/queue.php разрешены только классы-наследники Modules\\Queue\\Job');
            }
            $this->jobClasses[$jobClass] = $jobClass;
        }
    }

    public function create(QueueEnvelope $envelope): Job
    {
        $jobClass = $this->jobClasses[$envelope->type] ?? null;
        if ($jobClass === null) {
            throw new QueueException("Тип задания не зарегистрирован: {$envelope->type}");
        }

        return $jobClass::fromPayload($envelope->payload, $this->container);
    }
}
