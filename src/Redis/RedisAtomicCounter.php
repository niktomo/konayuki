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

    private const NEXT_SEQUENCE_SCRIPT = <<<'LUA'
        local key = KEYS[1]
        local initial = tonumber(ARGV[1])
        local ttl = tonumber(ARGV[2])
        if redis.call('EXISTS', key) == 0 then
            redis.call('SET', key, initial)
            if ttl > 0 then
                redis.call('EXPIRE', key, ttl)
            end
            return initial
        end
        return redis.call('INCR', key)
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

    public function nextSequence(string $key, int $initialValue, int $ttlSeconds): int
    {
        $fullKey = $this->keyPrefix.$key;
        $result = $this->callEval(self::NEXT_SEQUENCE_SCRIPT, [$fullKey, (string) $initialValue, (string) $ttlSeconds], 1);
        if (! is_int($result)) {
            throw new \RuntimeException("Redis nextSequence failed for key: {$fullKey}");
        }

        return $result;
    }

    public function wasReinitialized(): bool
    {
        $key = $this->keyPrefix.self::SENTINEL_KEY;
        $result = $this->callSet($key, '1', ['NX']);

        return $result === true || $result === 'OK';
    }

    /**
     * Wraps the duck-typed eval() call so PHPStan does not need to inspect
     * an unknown object's method signature.
     *
     * @param  list<string>  $args
     */
    private function callEval(string $script, array $args, int $numKeys): mixed
    {
        /** @var callable $eval */
        $eval = [$this->client, 'eval'];

        return $eval($script, $args, $numKeys);
    }

    /**
     * @param  array<int|string, mixed>  $options
     */
    private function callSet(string $key, string $value, array $options): mixed
    {
        /** @var callable $set */
        $set = [$this->client, 'set'];

        return $set($key, $value, $options);
    }
}
