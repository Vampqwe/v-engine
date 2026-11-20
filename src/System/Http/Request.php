<?php

declare(strict_types=1);

namespace VEngine\System\Http;

use VEngine\System\Config\ConfigInterface;
use VEngine\System\Map\MapInterface;

final class Request implements RequestInterface
{
    private string $method;
    private string $uri;
    private array $queryParams;
    private array $bodyParams;
    private array $headers;
    private array $attributes = [];

    public function __construct(
        private readonly MapInterface $map,
        private readonly ConfigInterface $config
    ) {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $rawUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $this->uri = $rawUri !== '/' ? rtrim($rawUri, '/') : '/';
        $this->queryParams = $_GET;
        $this->headers = $this->extractHeaders($_SERVER);
        $this->bodyParams = $this->extractBody();
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->map->get($this->queryParams, $key, $default);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->map->get($this->bodyParams, $key, $default);
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->bodyParams);
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? $default;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function ip(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (filter_var($remoteAddr, FILTER_VALIDATE_IP) === false) {
            return '127.0.0.1';
        }

        // Читаем заголовки прокси только если запрос пришел от доверенного узла
        if ($this->isFromTrustedProxy($remoteAddr)) {
            $forwardedFor = $this->header('x-forwarded-for');
            if (is_string($forwardedFor) && $forwardedFor !== '') {
                $ips = array_map('trim', explode(',', $forwardedFor));
                // Идем справа налево: берем первый IP, который не является доверенным прокси
                for ($i = count($ips) - 1; $i >= 0; $i--) {
                    $candidate = $ips[$i];
                    if (filter_var($candidate, FILTER_VALIDATE_IP) !== false && !$this->isFromTrustedProxy($candidate)) {
                        return $candidate;
                    }
                }
            }

            $realIp = $this->header('x-real-ip');
            if (is_string($realIp) && filter_var($realIp, FILTER_VALIDATE_IP) !== false) {
                return $realIp;
            }
        }

        return $remoteAddr;
    }

    private function isFromTrustedProxy(string $ip): bool
    {
        /** @var array<int, string> $trustedProxies */
        $trustedProxies = (array) $this->config->get('app', 'trusted_proxies', []);

        foreach ($trustedProxies as $proxy) {
            if ($proxy === '*') {
                return true;
            }
            if ($proxy === $ip) {
                return true;
            }
            if (str_contains($proxy, '/') && $this->ipInCidr($ip, $proxy)) {
                return true;
            }
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bitsStr] = explode('/', $cidr, 2);
        $bits = (int) $bitsStr;

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits > 0) {
            $mask = chr((0xFF << (8 - $remainingBits)) & 0xFF);
            if ((ord($ipBin[$fullBytes]) & ord($mask)) !== (ord($subnetBin[$fullBytes]) & ord($mask))) {
                return false;
            }
        }

        return true;
    }

    private function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    private function extractBody(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }

        $contentType = (string) $this->header('content-type', '');
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return [];
    }
}
