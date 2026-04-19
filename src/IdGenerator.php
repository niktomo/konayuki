<?php

declare(strict_types=1);

namespace Konayuki;

final class IdGenerator
{
    public function __construct(
        private readonly AtomicCounter $counter,
        private readonly Clock $clock,
        private readonly Layout $layout,
        private readonly TimestampStrategy $timestamp,
        private readonly int $workerId,
    ) {
        if ($workerId < 0 || $workerId > $layout->maxWorkerId) {
            throw new \InvalidArgumentException(
                "workerId out of layout range: {$workerId} (max: {$layout->maxWorkerId})"
            );
        }
    }

    public function next(): SnowflakeId
    {
        // Detected on every call so that a wipe occurring mid-instance-lifetime is also caught.
        // After wipe, per-ms sequence counters are gone — wait one ms so post-wipe IDs land on a
        // strictly greater timestamp than any pre-wipe ones.
        if ($this->counter->wasReinitialized()) {
            $this->clock->sleepMicroseconds(1000);
        }
        while (true) {
            $relativeTs = $this->timestamp->compute($this->clock, $this->layout->epochMs);
            if ($relativeTs < 0 || $relativeTs > $this->layout->maxTimestamp) {
                throw new \RuntimeException("Timestamp out of layout range: {$relativeTs}");
            }
            $key = sprintf('konayuki:seq:%d:%d', $this->workerId, $relativeTs);
            $rawSeq = $this->counter->increment($key, 2);
            $sequence = $rawSeq - 1;

            if ($sequence <= $this->layout->maxSequence) {
                return SnowflakeId::compose($relativeTs, $this->workerId, $sequence, $this->layout);
            }
            // Sequence exhausted in this ms window. Wait until the timestamp advances.
            do {
                $this->clock->sleepMicroseconds(100);
            } while ($this->timestamp->compute($this->clock, $this->layout->epochMs) <= $relativeTs);
        }
    }
}
