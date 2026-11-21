<?php

declare(strict_types=1);

namespace VEngine\System\Console;

use RuntimeException;

final class ArrayInput implements InputInterface
{
    /**
     * @param array<string, string> $arguments
     * @param array<string, string|bool> $options
     */
    public function __construct(
        private readonly array $arguments = [],
        private readonly array $options = [],
        private readonly mixed $inputStream = null,
        private readonly ?OutputInterface $promptOutput = null,
    ) {}

    /**
     * @param array<int, string> $tokens
     */
    public static function fromTokens(array $tokens, Command $command, OutputInterface $output): self
    {
        $argumentDefinitions = $command->arguments();
        $optionDefinitions = $command->options();
        $arguments = [];
        $options = [];
        $position = 0;

        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (!str_starts_with($token, '--')) {
                $definition = array_keys($argumentDefinitions)[$position] ?? null;
                if ($definition === null) {
                    throw new \InvalidArgumentException(
                        $command->extraArgumentsMessage() ?? "Лишний аргумент: {$token}",
                    );
                }
                $arguments[$definition] = $token;
                $position++;
                continue;
            }

            [$name, $value] = array_pad(explode('=', substr($token, 2), 2), 2, null);
            if ($name === 'help') {
                $options['help'] = true;
                continue;
            }
            if (!array_key_exists($name, $optionDefinitions)) {
                throw new \InvalidArgumentException("Неизвестная опция: --{$name}");
            }

            if ($optionDefinitions[$name]['value'] ?? false) {
                if ($value === null) {
                    $value = $tokens[++$index] ?? null;
                }
                if (!is_string($value) || str_starts_with($value, '--')) {
                    throw new \InvalidArgumentException("Опция --{$name} ожидает значение");
                }
                $options[$name] = $value;
            } else {
                if ($value !== null) {
                    throw new \InvalidArgumentException("Опция --{$name} не принимает значение");
                }
                $options[$name] = true;
            }
        }

        foreach ($argumentDefinitions as $name => $definition) {
            if (($definition['required'] ?? false) && !array_key_exists($name, $arguments)) {
                throw new \InvalidArgumentException("Не указан обязательный аргумент: {$name}");
            }
        }

        return new self($arguments, $options, STDIN, $output);
    }

    public function argument(string $name, ?string $default = null): ?string
    {
        return $this->arguments[$name] ?? $default;
    }

    public function option(string $name, string|bool|null $default = null): string|bool|null
    {
        return $this->options[$name] ?? $default;
    }

    public function ask(string $question, string $default = ''): string
    {
        return $this->readLine($question, $default);
    }

    public function askHidden(string $question): string
    {
        if (!is_resource($this->inputStream) || $this->promptOutput === null) {
            throw new RuntimeException('Для этого действия требуется интерактивный ввод в терминале');
        }

        $this->promptOutput->write($question);
        if (DIRECTORY_SEPARATOR === '\\') {
            return $this->readWindowsSecret();
        }

        $this->setTerminalEcho(false);
        try {
            $line = fgets($this->inputStream);
        } finally {
            $this->setTerminalEcho(true);
            $this->promptOutput->writeln();
        }

        return $line === false ? '' : rtrim($line, "\r\n");
    }

    private function readLine(string $question, string $default): string
    {
        if (!is_resource($this->inputStream) || $this->promptOutput === null) {
            throw new RuntimeException('Для этого действия требуется интерактивный ввод в терминале');
        }

        $this->promptOutput->write($question);
        $line = fgets($this->inputStream);
        $value = $line === false ? '' : trim($line);
        return $value !== '' ? $value : $default;
    }

    private function setTerminalEcho(bool $enabled): void
    {
        $command = $enabled ? 'echo' : '-echo';
        $process = @proc_open(
            ['stty', $command],
            [0 => $this->inputStream, 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true],
        );
        if (!is_resource($process) || proc_close($process) !== 0) {
            throw new RuntimeException('Не удалось отключить отображение ввода в терминале');
        }
    }

    private function readWindowsSecret(): string
    {
        $script = '$secure = Read-Host -AsSecureString; '
            . '$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure); '
            . 'try { [Console]::Out.Write([Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)) } '
            . 'finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }';
        $systemRoot = getenv('SystemRoot') ?: getenv('WINDIR');
        $executables = array_filter([
            is_string($systemRoot) && $systemRoot !== ''
                ? $systemRoot . '\System32\WindowsPowerShell\v1.0\powershell.exe'
                : null,
            'powershell.exe',
            'pwsh.exe',
        ]);

        foreach (array_unique($executables) as $executable) {
            $process = @proc_open(
                [$executable, '-NoProfile', '-Command', $script],
                [
                    0 => $this->inputStream,
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
                null,
                null,
                ['bypass_shell' => true],
            );
            if (!is_resource($process)) {
                continue;
            }

            $secret = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            if ($exitCode === 0 && is_string($secret)) {
                return rtrim($secret, "\r\n");
            }
        }

        throw new RuntimeException('Не удалось запустить PowerShell для скрытого ввода пароля');
    }
}
