<?php

declare(strict_types=1);

namespace Konayuki;

interface AtomicCounter
{
    /**
     * Atomically obtain the next sequence value at $key.
     *
     * - If the key does not exist: initialize it to $initialValue with TTL $ttlSeconds,
     *   and return $initialValue.
     * - If the key exists: atomically increment by 1 and return the new value.
     *
     * Implementations must be safe under concurrent access (multi-process, multi-thread):
     * the init-or-increment decision must be atomic, otherwise two callers could both
     * observe "key does not exist" and both initialize, losing one of the values.
     */
    public function nextSequence(string $key, int $initialValue, int $ttlSeconds): int;

    /**
     * Returns true if the storage has been re-initialized since the last process boot
     * (e.g., APCu cache wipe). Used by IdGenerator to add a safety millisecond before
     * issuing the first ID after a wipe.
     */
    public function wasReinitialized(): bool;
}
