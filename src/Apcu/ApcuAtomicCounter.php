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
    private const SENTINEL_KEY = 'konayuki:alive';

    public function increment(string $key, int $ttlSeconds): int
    {
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
        return apcu_add(self::SENTINEL_KEY, 1, 0);
    }
}
