<?php

declare(strict_types=1);

namespace Konayuki;

/**
 * Adds random ±jitterMs noise to the timestamp.
 *
 * Use only in local development to spread IDs across shards when traffic is too low
 * to naturally fill millisecond windows. Breaks k-sortable ordering — never use in
 * production.
 */
final class JitteredTimestamp implements TimestampStrategy
{
    public function __construct(public readonly int $jitterMs)
    {
        if ($jitterMs < 1) {
            throw new \InvalidArgumentException('jitterMs must be >= 1.');
        }
    }

    public function compute(Clock $clock, int $epochMs): int
    {
        return $clock->nowMs() - $epochMs + random_int(-$this->jitterMs, $this->jitterMs);
    }
}
