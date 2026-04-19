<?php

declare(strict_types=1);

namespace Konayuki;

final class RealTimestamp implements TimestampStrategy
{
    public function compute(Clock $clock, int $epochMs): int
    {
        return $clock->nowMs() - $epochMs;
    }
}
