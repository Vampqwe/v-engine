<?php

declare(strict_types=1);

namespace VEngine\System\Console;

final class BufferedOutput implements OutputInterface
{
    private string $content = '';

    public function write(string $message): void
    {
        $this->content .= $message;
    }

    public function writeln(string $message = ''): void
    {
        $this->content .= $message . PHP_EOL;
    }

    public function error(string $message): void
    {
        $this->content .= '[ERROR] ' . $message . PHP_EOL;
    }

    public function table(array $headers, array $rows): void
    {
        $this->writeln(implode("\t", $headers));
        foreach ($rows as $row) {
            $this->writeln(implode("\t", $row));
        }
    }

    public function content(): string
    {
        return $this->content;
    }
}
