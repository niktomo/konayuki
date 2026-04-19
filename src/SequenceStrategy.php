<?php

declare(strict_types=1);

namespace Konayuki;

interface SequenceStrategy
{
    /**
     * Returns the initial sequence value to use for a fresh (worker_id, ms) window.
     *
     * Must return an int in [0, $maxSequence].
     *
     * - MonotonicSequenceStrategy: always 0 (production default — pure k-sortable).
     * - RandomSequenceStrategy: random_int(0, $maxSequence) (local dev only —
     *   spreads IDs across shards when traffic is too low to fill ms windows).
     */
    public function initialValue(int $maxSequence): int;
}
