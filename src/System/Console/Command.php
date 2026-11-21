<?php

declare(strict_types=1);

namespace VEngine\System\Console;

abstract class Command
{
    /**
     * @param array<string, array{description?: string, required?: bool}> $arguments
     * @param array<string, array{description?: string, value?: bool}> $options
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly array $arguments = [],
        private readonly array $options = [],
        private readonly ?string $extraArgumentsMessage = null,
    ) {
        if (preg_match('/\A[a-z][a-z0-9:_-]*\z/', $name) !== 1) {
            throw new \InvalidArgumentException("Некорректное имя команды: {$name}");
        }
    }

    final public function name(): string
    {
        return $this->name;
    }

    final public function description(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, array{description?: string, required?: bool}>
     */
    final public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return array<string, array{description?: string, value?: bool}>
     */
    final public function options(): array
    {
        return $this->options;
    }

    final public function extraArgumentsMessage(): ?string
    {
        return $this->extraArgumentsMessage;
    }

    abstract public function handle(InputInterface $input, OutputInterface $output): int;
}
