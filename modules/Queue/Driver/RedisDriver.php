<?php

declare(strict_types=1);

namespace Modules\Queue\Driver;

use JsonException;
use Modules\Queue\QueueDriverInterface;
use Modules\Queue\QueueEnvelope;
use Modules\Queue\QueueException;
use Modules\Queue\QueueName;
use Modules\Queue\QueueReservation;
use Redis;
use Throwable;
use VEngine\System\Redis\RedisClient;
use VEngine\System\TimeDate\TimeDateInterface;

final class RedisDriver implements QueueDriverInterface
{
    private const RESERVE_SCRIPT = <<<'LUA'
local expired = redis.call('ZRANGEBYSCORE', KEYS[3], '-inf', ARGV[1], 'LIMIT', 0, 100)
for _, token in ipairs(expired) do
    local id = string.sub(token, 1, 32)
    local payload = redis.call('HGET', KEYS[4], id)
    if payload then
        redis.call('LPUSH', KEYS[1], payload)
        redis.call('HDEL', KEYS[4], id)
    end
    redis.call('ZREM', KEYS[3], token)
end

local delayed = redis.call('ZRANGEBYSCORE', KEYS[2], '-inf', ARGV[1], 'LIMIT', 0, 100)
for _, payload in ipairs(delayed) do
    redis.call('ZREM', KEYS[2], payload)
    redis.call('LPUSH', KEYS[1], payload)
end

local payload = redis.call('RPOP', KEYS[1])
if not payload then
    return nil
end
local job = cjson.decode(payload)
redis.call('HSET', KEYS[4], job.id, payload)
local token = job.id .. ':' .. ARGV[3]
redis.call('ZADD', KEYS[3], ARGV[2], token)
return {token, payload}
LUA;

    private const ACK_SCRIPT = <<<'LUA'
if not redis.call('ZSCORE', KEYS[2], ARGV[1]) then
    return 0
end
redis.call('HDEL', KEYS[1], string.sub(ARGV[1], 1, 32))
redis.call('ZREM', KEYS[2], ARGV[1])
return 1
LUA;

    private const RELEASE_SCRIPT = <<<'LUA'
if not redis.call('ZSCORE', KEYS[3], ARGV[1]) then
    return 0
end
redis.call('HDEL', KEYS[4], string.sub(ARGV[1], 1, 32))
redis.call('ZREM', KEYS[3], ARGV[1])
if tonumber(ARGV[3]) > tonumber(ARGV[2]) then
    redis.call('ZADD', KEYS[2], ARGV[3], ARGV[4])
else
    redis.call('LPUSH', KEYS[1], ARGV[4])
end
return 1
LUA;

    public function __construct(
        private readonly RedisClient $redisClient,
        private readonly TimeDateInterface $timeDate,
    ) {}

    public function enqueue(QueueEnvelope $envelope): void
    {
        $keys = $this->keys($envelope->queue);
        $payload = $this->encode($envelope);
        $redis = $this->connection();
        $result = $envelope->availableAt > $this->timeDate->timestamp()
            ? $redis->zAdd($keys['delayed'], $envelope->availableAt, $payload)
            : $redis->lPush($keys['ready'], $payload);
        if ($result === false) {
            throw new QueueException('Redis не смог поставить задание в очередь');
        }
    }

    public function reserve(string $queue, int $now, int $visibilityTimeout): ?QueueReservation
    {
        QueueName::validate($queue);
        if ($visibilityTimeout < 1) {
            throw new QueueException('Visibility timeout должен быть положительным');
        }

        $keys = $this->keys($queue);
        $result = $this->connection()->eval(
            self::RESERVE_SCRIPT,
            [
                $keys['ready'],
                $keys['delayed'],
                $keys['leases'],
                $keys['processing'],
                $now,
                $now + $visibilityTimeout,
                bin2hex(random_bytes(8)),
            ],
            4,
        );
        if ($result === false || $result === null) {
            return null;
        }
        if (!is_array($result) || !isset($result[0], $result[1]) || !is_string($result[0]) || !is_string($result[1])) {
            throw new QueueException('Redis вернул некорректное reservation задание');
        }

        try {
            $envelope = QueueEnvelope::fromJson($result[1]);
        } catch (JsonException $exception) {
            throw new QueueException('Redis вернул некорректный JSON задания', $exception);
        }

        return new QueueReservation($envelope, $result[0]);
    }

    public function acknowledge(QueueReservation $reservation): void
    {
        $keys = $this->keys($reservation->envelope->queue);
        $result = $this->connection()->eval(
            self::ACK_SCRIPT,
            [$keys['processing'], $keys['leases'], $reservation->token],
            2,
        );
        if ($result !== 1) {
            throw new QueueException('Не удалось подтвердить обработку Redis-задания');
        }
    }

    public function release(QueueReservation $reservation, QueueEnvelope $envelope): void
    {
        $keys = $this->keys($envelope->queue);
        $result = $this->connection()->eval(
            self::RELEASE_SCRIPT,
            [
                $keys['ready'],
                $keys['delayed'],
                $keys['leases'],
                $keys['processing'],
                $reservation->token,
                $this->timeDate->timestamp(),
                $envelope->availableAt,
                $this->encode($envelope),
            ],
            4,
        );
        if ($result !== 1) {
            throw new QueueException('Не удалось вернуть задание в Redis-очередь');
        }
    }

    /**
     * @return array{ready: string, delayed: string, leases: string, processing: string}
     */
    private function keys(string $queue): array
    {
        QueueName::validate($queue);
        $prefix = 'queue:' . $queue . ':';
        return [
            'ready'      => $prefix . 'ready',
            'delayed'    => $prefix . 'delayed',
            'leases'     => $prefix . 'leases',
            'processing' => $prefix . 'processing',
        ];
    }

    private function connection(): Redis
    {
        try {
            return $this->redisClient->connection();
        } catch (Throwable $exception) {
            throw new QueueException('Не удалось подключиться к Redis-очереди', $exception);
        }
    }

    private function encode(QueueEnvelope $envelope): string
    {
        try {
            return $envelope->toJson();
        } catch (JsonException $exception) {
            throw new QueueException('Payload задания должен быть сериализуем в JSON', $exception);
        }
    }
}
