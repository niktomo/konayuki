<?php

declare(strict_types=1);

namespace Konayuki\Sequence;

use Konayuki\SequenceStrategy;

/**
 * Always starts a fresh (worker_id, ms) window from 0. Production default.
 *
 * Combined with the per-(worker, ms) atomic counter, this yields strictly
 * k-sortable IDs — sequence reflects the actual ordering of next() calls
 * within a millisecond.
 */
final class MonotonicSequenceStrategy implements SequenceStrategy
{
    public function initialValue(int $maxSequence): int
    {
        return 0;
    }
}
