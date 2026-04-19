<?php

declare(strict_types=1);

namespace Konayuki\Tests\Unit;

use Konayuki\InMemory\InMemoryAtomicCounter;
use PHPUnit\Framework\TestCase;

final class InMemoryAtomicCounterTest extends TestCase
{
    public function test_first_call_returns_initial_value(): void
    {
        // Arrange
        $counter = new InMemoryAtomicCounter;

        // Act + Assert
        self::assertSame(0, $counter->nextSequence('k', 0, 2), 'first call with initial=0 returns 0');
        self::assertSame(1, $counter->nextSequence('k', 0, 2), 'second call increments to 1');
        self::assertSame(2, $counter->nextSequence('k', 0, 2), 'third call increments to 2');
    }

    public function test_first_call_honors_nonzero_initial_value(): void
    {
        // Arrange
        $counter = new InMemoryAtomicCounter;

        // Act
        $a = $counter->nextSequence('k', 700, 2);
        $b = $counter->nextSequence('k', 700, 2);
        $c = $counter->nextSequence('k', 700, 2);

        // Assert — initial value is consumed once; subsequent calls increment from there
        self::assertSame(700, $a, 'first call returns initial value 700');
        self::assertSame(701, $b, 'second call increments to 701');
        self::assertSame(702, $c, 'third call increments to 702');
    }

    public function test_different_keys_are_independent(): void
    {
        // Arrange
        $counter = new InMemoryAtomicCounter;

        // Act
        $a = $counter->nextSequence('a', 0, 2);
        $b = $counter->nextSequence('b', 0, 2);

        // Assert
        self::assertSame(0, $a, 'key a starts at initial');
        self::assertSame(0, $b, 'key b also starts at initial (independent)');
    }

    public function test_wasreinitialized_returns_true_on_first_call_only(): void
    {
        // Arrange
        $counter = new InMemoryAtomicCounter;

        // Act
        $first = $counter->wasReinitialized();
        $second = $counter->wasReinitialized();

        // Assert
        self::assertTrue($first, 'First call detects fresh counter');
        self::assertFalse($second, 'Subsequent calls report no reinit');
    }

    public function test_clear_resets_state(): void
    {
        // Arrange
        $counter = new InMemoryAtomicCounter;
        $counter->nextSequence('k', 0, 2);
        $counter->wasReinitialized();

        // Act
        $counter->clear();

        // Assert
        self::assertSame(0, $counter->nextSequence('k', 0, 2), 'counter reset, next call returns initial');
        self::assertTrue($counter->wasReinitialized(), 'Reinit flag reset');
    }
}
