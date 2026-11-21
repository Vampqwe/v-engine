<?php

declare(strict_types=1);

$driver = $_ENV['QUEUE_DRIVER'] ?? $_SERVER['QUEUE_DRIVER'] ?? 'file';
if (!in_array($driver, ['sync', 'file', 'redis'], true)) {
    throw new \RuntimeException('QUEUE_DRIVER должен быть sync, file или redis');
}

$visibilityTimeout = filter_var(
    $_ENV['QUEUE_VISIBILITY_TIMEOUT'] ?? $_SERVER['QUEUE_VISIBILITY_TIMEOUT'] ?? '300',
    FILTER_VALIDATE_INT,
);
if ($visibilityTimeout === false || $visibilityTimeout < 1 || $visibilityTimeout > 86400) {
    throw new \RuntimeException('QUEUE_VISIBILITY_TIMEOUT должен быть числом от 1 до 86400');
}

$jobs = $_ENV['QUEUE_JOBS'] ?? $_SERVER['QUEUE_JOBS'] ?? '';
$jobClasses = $jobs === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $jobs))));

return [
    'driver'             => $driver,
    'visibility_timeout' => $visibilityTimeout,
    'jobs'               => $jobClasses,
];
