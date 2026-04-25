<?php

declare(strict_types=1);

namespace Konayuki\Apcu;

use Konayuki\AtomicCounter;

/**
 * APCu-backed atomic counter.
 *
 * Cache-loss safety:
 * - Each counter key is namespaced by timestamp (caller embeds the ms in $key),
 *   and given a short TTL ($ttlSeconds, typically 2). After APCu wipe, fresh keys
 *   start at 1 again — but since the timestamp portion advances monotonically,
 *   the new ID space cannot collide with any in-flight ms key.
 * - The only collision window is "wipe + same-ms request", which IdGenerator
 *   guards against by detecting the wipe via {@see wasReinitialized()} and
 *   waiting one ms before issuing.
 *
 * Per-PID sentinel:
 * - The sentinel key embeds getmypid() so that EVERY process independently
 *   detects a wipe. A shared sentinel would let only the first process to call
 *   apcu_add "consume" the detection signal; all others would see false and
 *   skip the safety wait, risking same-ms collisions.
 */
final class ApcuAtomicCounter implements AtomicCounter
{
    public const DEFAULT_KEY_PREFIX = 'konayuki:seq';

    private readonly string $sentinelKey;

    public function __construct(string $keyPrefix = self::DEFAULT_KEY_PREFIX)
    {
        $this->sentinelKey = sprintf('%s:_sentinel:%d', $keyPrefix, getmypid());
    }

    public function nextSequence(string $key, int $initialValue, int $ttlSeconds): int
    {
        // apcu_add is atomic: only the first caller for a given key succeeds.
        // Losers fall through to apcu_inc, which is also atomic and now sees
        // the key populated → standard increment path.
        if (apcu_add($key, $initialValue, $ttlSeconds)) {
            return $initialValue;
        }
        $value = apcu_inc($key, 1, $success, $ttlSeconds);
        if (! $success || ! is_int($value)) {
            throw new \RuntimeException("apcu_inc failed for key {$key}");
        }

        return $value;
    }

    public function wasReinitialized(): bool
    {
        // apcu_add returns true only if the key was absent (APCu wipe or first run for this PID).
        // Per-PID key means every process independently detects a wipe — no shared sentinel race.
        return apcu_add($this->sentinelKey, 1, 0);
    }
}
