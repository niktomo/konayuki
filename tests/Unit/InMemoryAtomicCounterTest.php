<?php

declare(strict_types=1);

namespace Konayuki\Tests\Unit;

use Konayuki\InMemory\InMemoryAtomicCounter;
use PHPUnit\Framework\TestCase;

final class InMemoryAtomicCounterTest extends TestCase
{
    public function test_increment_returns_monotonically_increasing_values(): void
    {
        // Arrange
        $counter = new InMemoryAtomicCounter;

        // Act
        $values = [
            $counter->increment('k', 2),
            $counter->increment('k', 2),
            $counter->increment('k', 2),
        ];

        // Assert
        self::assertSame([1, 2, 3], $values, 'Increment returns 1, 2, 3 on same key');
    }

    public function test_different_keys_are_independent(): void
    {
        // Arrange
        $counter = new InMemoryAtomicCounter;

        // Act
        $a = $counter->increment('a', 2);
        $b = $counter->increment('b', 2);

        // Assert
        self::assertSame(1, $a, 'key a starts at 1');
        self::assertSame(1, $b, 'key b also starts at 1 (independent)');
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
        $counter->increment('k', 2);
        $counter->wasReinitialized();

        // Act
        $counter->clear();

        // Assert
        self::assertSame(1, $counter->increment('k', 2), 'Counter reset to 0, next inc returns 1');
        self::assertTrue($counter->wasReinitialized(), 'Reinit flag reset');
    }
}
