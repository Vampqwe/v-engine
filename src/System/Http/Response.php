<?php

declare(strict_types=1);

namespace VEngine\System\Http;

final class Response implements ResponseInterface
{
    public function __construct(
        private string $body = '',
        private int $statusCode = 200,
        private array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
    ) {
        foreach ($this->headers as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new \InvalidArgumentException('HTTP-заголовки должны иметь строковые имена и значения');
            }
            self::assertHeader($name, $value);
        }
    }

    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(array $data, int $status = 200): self
    {
        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );
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
        self::assertHeader($name, $value);
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    private static function assertHeader(string $name, string $value): void
    {
        if (
            preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/", $name) !== 1
            || preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value) === 1
        ) {
            throw new \InvalidArgumentException('Некорректное имя или значение HTTP-заголовка');
        }
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
