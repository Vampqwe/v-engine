<?php

declare(strict_types=1);

namespace VEngine\System\Http;

final class Response implements ResponseInterface
{
    public function __construct(
        private string $body = '',
        private int $statusCode = 200,
        private array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
    ) {}

    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(array $data, int $status = 200): self
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return new self($json, $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public function withStatus(int $code): self
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        return $clone;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->body;
    }
}
