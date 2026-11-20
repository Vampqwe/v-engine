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
        $site = (string) $this->config->env('APP_SITE', 'Corporate');
        $connectTimeout = (float) $this->config->get('redis', 'connect_timeout', 1.0);
        $readTimeout = (float) $this->config->get('redis', 'timeout', 0.5);

        try {
            $redis = new Redis();
            if (!$redis->connect($host, $port, $connectTimeout)) {
                throw new RedisException("Не удалось подключиться к Redis ({$host}:{$port})");
            }

            if (!$redis->setOption(Redis::OPT_READ_TIMEOUT, $readTimeout)) {
                throw new RedisException('Не удалось настроить таймаут чтения Redis');
            }

            if ($password !== '') {
                if (!$redis->auth($password)) {
                    throw new RedisException('Redis отклонил аутентификацию');
                }
            }

            if ($database > 0) {
                if (!$redis->select($database)) {
                    throw new RedisException('Не удалось выбрать Redis database');
                }
            }

            if (!$redis->setOption(Redis::OPT_PREFIX, RedisNamespace::forSite($prefix, $site))) {
                throw new RedisException('Не удалось настроить namespace Redis для выбранного сайта');
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
        $client = $this->client;
        $this->client = null;

        if ($client !== null) {
            $client->close();
        }
    }
}
