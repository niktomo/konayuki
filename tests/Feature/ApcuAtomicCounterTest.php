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

    public function test_increment_returns_monotonic_values(): void
    {
        // Given
        $counter = new ApcuAtomicCounter;

        // When
        $a = $counter->increment('konayuki:test:k', 2);
        $b = $counter->increment('konayuki:test:k', 2);
        $c = $counter->increment('konayuki:test:k', 2);

        // Then
        self::assertSame([1, 2, 3], [$a, $b, $c], 'apcu_inc returns 1, 2, 3 on same key');
    }

    public function test_different_keys_are_independent(): void
    {
        // Given
        $counter = new ApcuAtomicCounter;

        // When
        $a = $counter->increment('konayuki:test:a', 2);
        $b = $counter->increment('konayuki:test:b', 2);

        // Then
        self::assertSame(1, $a, 'key a starts at 1');
        self::assertSame(1, $b, 'key b also starts at 1 (independent)');
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
