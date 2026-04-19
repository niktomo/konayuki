<?php

declare(strict_types=1);

namespace Konayuki;

final readonly class Layout
{
    public int $maxTimestamp;

    public int $maxWorkerId;

    public int $maxSequence;

    public int $workerShift;

    public int $timestampShift;

    public function __construct(
        public int $epochMs,
        public int $timestampBits = 41,
        public int $workerBits = 10,
        public int $sequenceBits = 12,
    ) {
        if ($timestampBits + $workerBits + $sequenceBits !== 63) {
            throw new \InvalidArgumentException(
                'Konayuki layout must total 63 bits (1 sign bit reserved).'
            );
        }
        $this->maxTimestamp = (1 << $timestampBits) - 1;
        $this->maxWorkerId = (1 << $workerBits) - 1;
        $this->maxSequence = (1 << $sequenceBits) - 1;
        $this->workerShift = $sequenceBits;
        $this->timestampShift = $sequenceBits + $workerBits;
    }

    public static function default(): self
    {
        // 2026-01-01 00:00:00 UTC in milliseconds
        return new self(epochMs: 1_767_225_600_000);
    }
}
