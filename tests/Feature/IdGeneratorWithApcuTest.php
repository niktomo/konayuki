<?php

declare(strict_types=1);

namespace Konayuki\Tests\Feature;

use Konayuki\Apcu\ApcuAtomicCounter;
use Konayuki\IdGenerator;
use Konayuki\Layout;
use Konayuki\RealTimestamp;
use Konayuki\SystemClock;
use PHPUnit\Framework\TestCase;

final class IdGeneratorWithApcuTest extends TestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('apcu') || ! ini_get('apc.enable_cli')) {
            self::markTestSkipped('APCu extension or apc.enable_cli not available');
        }
        apcu_clear_cache();
    }

    public function test_emits_unique_ids_under_real_clock(): void
    {
        // Given — generator wired to APCu + system clock
        $generator = new IdGenerator(
            counter: new ApcuAtomicCounter,
            clock: new SystemClock,
            layout: Layout::default(),
            timestamp: new RealTimestamp,
            workerId: 1,
        );

        // When — emit a batch of IDs
        $ids = [];
        for ($i = 0; $i < 10_000; $i++) {
            $ids[] = $generator->next()->value;
        }

        // Then — all unique
        self::assertCount(10_000, array_unique($ids), 'No collisions in 10k IDs');
    }

    public function test_ids_are_monotonic_within_single_process(): void
    {
        // Given
        $generator = new IdGenerator(
            counter: new ApcuAtomicCounter,
            clock: new SystemClock,
            layout: Layout::default(),
            timestamp: new RealTimestamp,
            workerId: 2,
        );

        // When
        $previous = $generator->next()->value;
        for ($i = 0; $i < 1_000; $i++) {
            $current = $generator->next()->value;
            // Then — strictly ascending
            self::assertGreaterThan($previous, $current, "ID #{$i} not monotonic");
            $previous = $current;
        }
    }
}
