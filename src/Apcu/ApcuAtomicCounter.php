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
 */
final class ApcuAtomicCounter implements AtomicCounter
{
    public const DEFAULT_KEY_PREFIX = 'konayuki:seq';

    private readonly string $sentinelKey;

    public function __construct(string $keyPrefix = self::DEFAULT_KEY_PREFIX)
    {
        $this->sentinelKey = sprintf('%s:_sentinel', $keyPrefix);
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
        // apcu_add returns true only if the key was created (i.e. APCu was empty for this key).
        // Called on every next() so that a wipe occurring mid-instance-lifetime is also detected.
        // Race-safe: only one process wins the add; others see false → not reinitialized from their POV.
        return apcu_add($this->sentinelKey, 1, 0);
    }
}
