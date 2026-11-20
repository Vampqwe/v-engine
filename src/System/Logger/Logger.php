<?php

declare(strict_types=1);

namespace VEngine\System\Logger;

use VEngine\System\File\FileInterface;
use VEngine\System\TimeDate\TimeDateInterface;

final class Logger implements LoggerInterface
{
    private string $moduleName = 'system';
    public function __construct(
        private readonly string $logDir,
        private readonly FileInterface $file,
        private readonly TimeDateInterface $timeDate,
    ) {}
    public function forModule(string $moduleName): self
    {
        $clone = clone $this;
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $moduleName);
        $clone->moduleName = ($sanitized !== '' && $sanitized !== null) ? $sanitized : 'system';
        return $clone;
    }
    public function info(string $message, array $context = []): void
    {
        $this->writeLog('INFO', $message, $context);
    }
    public function error(string $message, array $context = []): void
    {
        $this->writeLog('ERROR', $message, $context);
    }
    private function writeLog(string $level, string $message, array $context): void
    {
        $date = $this->timeDate->now();
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$date}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        $path = "{$this->logDir}/{$this->moduleName}.log";
        $this->file->write($path, $line, true);
    }
}
