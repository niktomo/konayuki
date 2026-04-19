<?php

declare(strict_types=1);

namespace Konayuki;

final class IdGenerator
{
    private const MAX_ATTEMPTS = 10_000;

    public function __construct(
        private readonly AtomicCounter $counter,
        private readonly Clock $clock,
        private readonly Layout $layout,
        private readonly TimestampStrategy $timestamp,
        private readonly SequenceStrategy $sequence,
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
        // After wipe, per-ms sequence counters are gone — busy-wait until the timestamp strictly
        // advances so post-wipe IDs cannot collide with any in-flight pre-wipe ms key.
        if ($this->counter->wasReinitialized()) {
            $tsBefore = $this->timestamp->compute($this->clock, $this->layout->epochMs);
            do {
                $this->clock->sleepMicroseconds(1000);
            } while ($this->timestamp->compute($this->clock, $this->layout->epochMs) <= $tsBefore);
        }
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $relativeTs = $this->timestamp->compute($this->clock, $this->layout->epochMs);
            if ($relativeTs < 0 || $relativeTs > $this->layout->maxTimestamp) {
                throw new \RuntimeException("Timestamp out of layout range: {$relativeTs}");
            }
            $key = "konayuki:seq:{$this->workerId}:{$relativeTs}";
            $initialValue = $this->sequence->initialValue($this->layout->maxSequence);
            $sequence = $this->counter->nextSequence($key, $initialValue, 2);

            if ($sequence <= $this->layout->maxSequence) {
                return SnowflakeId::compose($relativeTs, $this->workerId, $sequence, $this->layout);
            }
            // Sequence exhausted in this ms window. Wait until the timestamp advances.
            do {
                $this->clock->sleepMicroseconds(100);
            } while ($this->timestamp->compute($this->clock, $this->layout->epochMs) <= $relativeTs);
        }
        throw new \RuntimeException(
            'IdGenerator::next() exceeded '.self::MAX_ATTEMPTS.' attempts — clock not advancing or sequence exhaustion is permanent'
        );
    }
}
