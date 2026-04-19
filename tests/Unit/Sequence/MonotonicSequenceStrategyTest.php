<?php

declare(strict_types=1);

namespace Konayuki\Tests\Unit\Sequence;

use Konayuki\Sequence\MonotonicSequenceStrategy;
use PHPUnit\Framework\TestCase;

final class MonotonicSequenceStrategyTest extends TestCase
{
    public function test_always_returns_zero_regardless_of_max_sequence(): void
    {
        // Arrange
        $strategy = new MonotonicSequenceStrategy;

        // Act + Assert
        self::assertSame(0, $strategy->initialValue(0), 'maxSequence=0 yields 0');
        self::assertSame(0, $strategy->initialValue(1), 'maxSequence=1 yields 0');
        self::assertSame(0, $strategy->initialValue(4095), 'maxSequence=4095 yields 0');
        self::assertSame(0, $strategy->initialValue(PHP_INT_MAX), 'large maxSequence yields 0');
    }

    public function test_is_deterministic_across_calls(): void
    {
        // Arrange
        $strategy = new MonotonicSequenceStrategy;

        // Act + Assert — repeated calls must always return same value
        for ($i = 0; $i < 100; $i++) {
            self::assertSame(0, $strategy->initialValue(4095), "call {$i} must return 0");
        }
    }
}
