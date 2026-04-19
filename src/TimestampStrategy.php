<?php

declare(strict_types=1);

namespace Konayuki;

interface TimestampStrategy
{
    /**
     * Return the timestamp portion (in ms, relative to the given epoch) for the next ID.
     * Must be monotonic within a single process under production strategies.
     */
    public function compute(Clock $clock, int $epochMs): int;
}
