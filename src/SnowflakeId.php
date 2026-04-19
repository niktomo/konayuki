<?php

declare(strict_types=1);

namespace Konayuki;

use Stringable;

final readonly class SnowflakeId implements Stringable
{
    private function __construct(
        public int $value,
        public Layout $layout,
    ) {}

    public static function compose(
        int $relativeTimestamp,
        int $workerId,
        int $sequence,
        Layout $layout,
    ): self {
        if ($relativeTimestamp < 0 || $relativeTimestamp > $layout->maxTimestamp) {
            throw new \InvalidArgumentException("relativeTimestamp out of range: {$relativeTimestamp}");
        }
        if ($workerId < 0 || $workerId > $layout->maxWorkerId) {
            throw new \InvalidArgumentException("workerId out of range: {$workerId}");
        }
        if ($sequence < 0 || $sequence > $layout->maxSequence) {
            throw new \InvalidArgumentException("sequence out of range: {$sequence}");
        }

        $value = ($relativeTimestamp << $layout->timestampShift)
            | ($workerId << $layout->workerShift)
            | $sequence;

        return new self($value, $layout);
    }

    public static function fromInt(int $value, Layout $layout): self
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('SnowflakeId must be non-negative.');
        }

        return new self($value, $layout);
    }

    public function timestamp(): int
    {
        return ($this->value >> $this->layout->timestampShift) + $this->layout->epochMs;
    }

    public function workerId(): int
    {
        return ($this->value >> $this->layout->workerShift) & $this->layout->maxWorkerId;
    }

    public function sequence(): int
    {
        return $this->value & $this->layout->maxSequence;
    }

    /**
     * Hash-based shard routing. Avoids two failure modes:
     *  - `value MOD N` clusters by timestamp under low traffic.
     *  - Direct `low_bits MOD N` collapses when N is a power-of-2 aligned with
     *    a bit-field boundary (e.g. shardCount=16 vs workerShift=12 → all
     *    workers route to the same shard).
     * crc32 provides cheap avalanche so every bit of the ID influences the result.
     */
    public function shardKey(int $shardCount): int
    {
        if ($shardCount < 1) {
            throw new \InvalidArgumentException('shardCount must be >= 1.');
        }

        return crc32((string) $this->value) % $shardCount;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
