<?php

declare(strict_types=1);

namespace Konayuki\Sequence;

use Konayuki\SequenceStrategy;

/**
 * Starts each fresh (worker_id, ms) window at a cryptographically random offset.
 *
 * **Intended for local development only.** Useful when downstream shard keys are
 * derived from sequence low-bits and traffic is too sparse to fill ms windows
 * naturally — without random seeding, almost every ID would land at sequence=0.
 *
 * After the random initial value, the per-window counter still increments
 * monotonically (atomic +1 per next()), so within one ms the IDs remain
 * distinct and ordering-friendly. K-sortable order across ms boundaries is
 * preserved (timestamp prefix dominates), but within one ms the relative
 * ordering of two IDs no longer reflects call order.
 *
 * **Do not use in production**: the counter exhausts the ms window earlier
 * (since you start mid-range), so high-throughput workers will block on the
 * "wait for next ms" path more often.
 */
final class RandomSequenceStrategy implements SequenceStrategy
{
    public function initialValue(int $maxSequence): int
    {
        if ($maxSequence < 0) {
            throw new \InvalidArgumentException("maxSequence must be >= 0, got {$maxSequence}");
        }

        return random_int(0, $maxSequence);
    }
}
