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
        private readonly ConfigInterface $config
    ) {}

    public function connection(): Redis
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if (!extension_loaded('redis')) {
            throw new RedisException('PHP-расширение ext-redis не установлено');
        }

        $host = (string) $this->config->env('REDIS_HOST', '127.0.0.1');
        $port = (int) $this->config->env('REDIS_PORT', 6379);
        $password = (string) $this->config->env('REDIS_PASSWORD', '');
        $database = (int) $this->config->env('REDIS_DB', 0);
        $prefix = (string) $this->config->env('REDIS_PREFIX', 'vengine:');

        try {
            $redis = new Redis();
            if (!$redis->connect($host, $port, 2.0)) {
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
}
