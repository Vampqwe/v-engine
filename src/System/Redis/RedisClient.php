<?php

declare(strict_types=1);

namespace VEngine\System\Redis;

use Redis;
use Throwable;
use VEngine\System\Config\ConfigInterface;

final class RedisClient
{
    private ?Redis $client = null;

    public function __construct(
        private readonly ConfigInterface $config,
    ) {}

    public function connection(): Redis
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if (!extension_loaded('redis')) {
            throw new RedisException('PHP-расширение ext-redis не установлено');
        }

        $host = (string) $this->config->get('redis', 'host', '127.0.0.1');
        $port = (int) $this->config->get('redis', 'port', 6379);
        $password = (string) $this->config->get('redis', 'password', '');
        $database = (int) $this->config->get('redis', 'database', 0);
        $prefix = (string) $this->config->get('redis', 'prefix', 'vengine:');
        $timeout = (float) $this->config->get('redis', 'timeout', 2.0);

        try {
            $redis = new Redis();
            if (!$redis->connect($host, $port, $timeout)) {
                throw new RedisException("Не удалось подключиться к Redis ({$host}:{$port})");
            }

            if ($password !== '') {
                $redis->auth($password);
            }

            if ($database > 0) {
                $redis->select($database);
            }

            if ($prefix !== '') {
                $redis->setOption(Redis::OPT_PREFIX, $prefix);
            }

            $this->client = $redis;
            return $this->client;
        } catch (Throwable $e) {
            if ($e instanceof RedisException) {
                throw $e;
            }
            throw new RedisException('Ошибка инициализации Redis: ' . $e->getMessage(), 0, $e);
        }
    }

    public function resetConnection(): void
    {
        $this->client = null;
    }
}
