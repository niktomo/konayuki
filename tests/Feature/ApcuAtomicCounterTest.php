<?php

declare(strict_types=1);

namespace Konayuki\Tests\Feature;

use Konayuki\Apcu\ApcuAtomicCounter;
use PHPUnit\Framework\TestCase;

final class ApcuAtomicCounterTest extends TestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('apcu') || ! ini_get('apc.enable_cli')) {
            self::markTestSkipped('APCu extension or apc.enable_cli not available');
        }
        apcu_clear_cache();
    }

    public function test_next_sequence_returns_monotonic_values(): void
    {
        // Given
        $counter = new ApcuAtomicCounter;

        // When
        $a = $counter->nextSequence('konayuki:test:k', 0, 2);
        $b = $counter->nextSequence('konayuki:test:k', 0, 2);
        $c = $counter->nextSequence('konayuki:test:k', 0, 2);

        // Then
        self::assertSame([0, 1, 2], [$a, $b, $c], 'first call returns initial (0), then increments');
    }

    public function test_next_sequence_honors_nonzero_initial_value(): void
    {
        // Given
        $counter = new ApcuAtomicCounter;

        // When
        $a = $counter->nextSequence('konayuki:test:init', 500, 2);
        $b = $counter->nextSequence('konayuki:test:init', 500, 2);

        // Then
        self::assertSame(500, $a, 'first call returns initial 500');
        self::assertSame(501, $b, 'second call increments from 500');
    }

    public function test_different_keys_are_independent(): void
    {
        // Given
        $counter = new ApcuAtomicCounter;

        // When
        $a = $counter->nextSequence('konayuki:test:a', 0, 2);
        $b = $counter->nextSequence('konayuki:test:b', 0, 2);

        // Then
        self::assertSame(0, $a, 'key a starts at initial');
        self::assertSame(0, $b, 'key b also starts at initial (independent)');
    }

    public function test_wasreinitialized_true_on_fresh_apcu(): void
    {
        // Given — APCu was just cleared in setUp()
        $counter = new ApcuAtomicCounter;

        // When
        $reinit = $counter->wasReinitialized();

        // Then
        self::assertTrue($reinit, 'fresh APCu reports reinitialization');
    }

    public function test_wasreinitialized_false_after_sentinel_set(): void
    {
        // Given
        $first = new ApcuAtomicCounter;
        $first->wasReinitialized();

        // When — second instance checks
        $second = new ApcuAtomicCounter;
        $reinit = $second->wasReinitialized();

        // Then
        self::assertFalse($reinit, 'second instance sees existing sentinel');
    }
}
