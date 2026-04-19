<?php

declare(strict_types=1);

namespace Konayuki\Tests\Unit;

use Konayuki\FrozenClock;
use Konayuki\IdGenerator;
use Konayuki\InMemory\InMemoryAtomicCounter;
use Konayuki\Layout;
use Konayuki\RealTimestamp;
use PHPUnit\Framework\TestCase;

final class IdGeneratorTest extends TestCase
{
    public function test_emits_monotonically_increasing_sequence_within_same_ms(): void
    {
        // Arrange
        $layout = Layout::default();
        $clock = new FrozenClock($layout->epochMs + 100);
        $counter = new InMemoryAtomicCounter;
        $generator = new IdGenerator($counter, $clock, $layout, new RealTimestamp, workerId: 1);

        // Act
        $a = $generator->next();
        $b = $generator->next();
        $c = $generator->next();

        // Assert
        self::assertSame(0, $a->sequence(), 'first sequence is 0');
        self::assertSame(1, $b->sequence(), 'second sequence is 1');
        self::assertSame(2, $c->sequence(), 'third sequence is 2');
        self::assertLessThan($b->value, $a->value, 'IDs strictly ascending (a<b)');
        self::assertLessThan($c->value, $b->value, 'IDs strictly ascending (b<c)');
    }

    public function test_sequence_resets_when_timestamp_advances(): void
    {
        // Arrange
        $layout = Layout::default();
        $clock = new FrozenClock($layout->epochMs + 100);
        $counter = new InMemoryAtomicCounter;
        $generator = new IdGenerator($counter, $clock, $layout, new RealTimestamp, workerId: 1);

        // Act
        $a = $generator->next();
        $clock->advance(1);
        $b = $generator->next();

        // Assert
        self::assertSame(0, $a->sequence(), 'first ms sequence starts at 0');
        self::assertSame(0, $b->sequence(), 'next ms sequence resets to 0');
        self::assertNotSame($a->timestamp(), $b->timestamp(), 'timestamps differ');
    }

    public function test_blocks_until_next_ms_when_sequence_exhausted(): void
    {
        // Arrange — pre-populate counter to one below max
        $layout = new Layout(epochMs: 0, timestampBits: 41, workerBits: 10, sequenceBits: 4);
        $clock = new FrozenClock(100);
        $counter = new InMemoryAtomicCounter;
        for ($i = 0; $i < $layout->maxSequence; $i++) {
            $counter->increment("konayuki:seq:1:{$clock->nowMs()}", 2);
        }
        $generator = new IdGenerator($counter, $clock, $layout, new RealTimestamp, workerId: 1);

        // Act — last ID in this ms
        $last = $generator->next();
        // Now exhausted; next call must wait for ms to advance
        $startMs = $clock->nowMs();

        // Inject ms advance via the FrozenClock's sleepMicroseconds (which advances time)
        $next = $generator->next();

        // Assert
        self::assertSame($layout->maxSequence, $last->sequence(), 'last ID uses max sequence');
        self::assertGreaterThan($startMs, $clock->nowMs(), 'clock advanced past starvation');
        self::assertSame(0, $next->sequence(), 'fresh ms starts sequence at 0');
    }

    public function test_rejects_out_of_range_worker_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new IdGenerator(
            new InMemoryAtomicCounter,
            new FrozenClock(0),
            Layout::default(),
            new RealTimestamp,
            workerId: 1024,
        );
    }

    public function test_safety_wait_on_next_call_after_apcu_reinit(): void
    {
        // Arrange — counter reports reinit on first wasReinitialized() call
        $layout = Layout::default();
        $clock = new FrozenClock($layout->epochMs + 100);
        $counter = new InMemoryAtomicCounter;
        $generator = new IdGenerator($counter, $clock, $layout, new RealTimestamp, workerId: 1);
        $startMs = $clock->nowMs();

        // Act — wipe detection now happens inside next(), not the constructor
        $generator->next();

        // Assert
        self::assertGreaterThan($startMs, $clock->nowMs(), 'Clock advanced by safety wait after reinit on first next()');
    }

    public function test_constructor_does_not_advance_the_clock(): void
    {
        // Arrange
        $layout = Layout::default();
        $clock = new FrozenClock($layout->epochMs + 100);
        $counter = new InMemoryAtomicCounter;
        $startMs = $clock->nowMs();

        // Act — wipe handling is deferred to next(); constructor must be inert wrt time
        new IdGenerator($counter, $clock, $layout, new RealTimestamp, workerId: 1);

        // Assert
        self::assertSame($startMs, $clock->nowMs(), 'Clock unaffected by constructor');
    }
}
