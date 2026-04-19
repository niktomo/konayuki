<?php

declare(strict_types=1);

namespace Konayuki\Redis;

use Konayuki\AtomicCounter;

/**
 * Redis-backed atomic counter — for distributed (multi-host) deployments.
 *
 * Uses a single Lua script that atomically INCRs the key and sets EXPIRE on first
 * creation (replicating ApcuAtomicCounter's TTL semantics). Survives across all
 * hosts that share the same Redis instance.
 *
 * Accepts any client exposing the phpredis-compatible API:
 *   - eval(string $script, array $args, int $numKeys): mixed
 *   - set(string $key, mixed $value, mixed $options): mixed
 *
 * Both phpredis (\Redis) and Predis (\Predis\Client) satisfy this contract.
 */
final class RedisAtomicCounter implements AtomicCounter
{
    private const SENTINEL_KEY = '__konayuki_alive__';

    private const INCR_SCRIPT = <<<'LUA'
        local val = redis.call('INCR', KEYS[1])
        if val == 1 and tonumber(ARGV[1]) > 0 then
            redis.call('EXPIRE', KEYS[1], ARGV[1])
        end
        return val
        LUA;

    public function __construct(
        private readonly object $client,
        public readonly string $keyPrefix = '',
    ) {
        if (! method_exists($client, 'eval') || ! method_exists($client, 'set')) {
            throw new \InvalidArgumentException(
                'Redis client must expose eval() and set() methods (phpredis or Predis compatible).'
            );
        }
    }

    public function increment(string $key, int $ttlSeconds): int
    {
        $fullKey = $this->keyPrefix.$key;
        /** @phpstan-ignore-next-line — dynamic dispatch on duck-typed client */
        $result = $this->client->eval(self::INCR_SCRIPT, [$fullKey, (string) $ttlSeconds], 1);
        if (! is_int($result)) {
            throw new \RuntimeException("Redis INCR failed for key: {$fullKey}");
        }

        return $result;
    }

    public function wasReinitialized(): bool
    {
        $key = $this->keyPrefix.self::SENTINEL_KEY;
        /** @phpstan-ignore-next-line — dynamic dispatch on duck-typed client */
        $result = $this->client->set($key, '1', ['NX']);

        return $result === true || $result === 'OK';
    }
}
