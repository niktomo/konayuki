<?php

declare(strict_types=1);

namespace Konayuki;

interface AtomicCounter
{
    /**
     * Atomically increment the counter at $key by 1 and return the post-increment value.
     * If the key does not exist, it is created with TTL $ttlSeconds and returns 1.
     *
     * Implementations must be safe under concurrent access (multiple processes / threads).
     */
    public function increment(string $key, int $ttlSeconds): int;

    /**
     * Returns true if the storage has been re-initialized since the last process boot
     * (e.g., APCu cache wipe). Used by IdGenerator to add a safety millisecond before
     * issuing the first ID after a wipe.
     */
    public function wasReinitialized(): bool;
}
