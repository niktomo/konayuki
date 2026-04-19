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
     * Use the low-order bits (sequence + worker) for shard routing.
     * Avoid using `value MOD N` directly — it would route by timestamp under low traffic.
     */
    public function shardKey(int $shardCount): int
    {
        if ($shardCount < 1) {
            throw new \InvalidArgumentException('shardCount must be >= 1.');
        }
        $low = $this->value & ((1 << $this->layout->timestampShift) - 1);

        return $low % $shardCount;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
