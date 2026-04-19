<?php

declare(strict_types=1);

namespace Konayuki\Tests\Unit\Sequence;

use Konayuki\Sequence\RandomSequenceStrategy;
use PHPUnit\Framework\TestCase;

final class RandomSequenceStrategyTest extends TestCase
{
    public function test_returns_value_within_zero_to_max_inclusive(): void
    {
        // Arrange
        $strategy = new RandomSequenceStrategy;
        $maxSequence = 4095;

        // Act + Assert
        for ($i = 0; $i < 200; $i++) {
            $value = $strategy->initialValue($maxSequence);
            self::assertGreaterThanOrEqual(0, $value, "iteration {$i}: value >= 0");
            self::assertLessThanOrEqual($maxSequence, $value, "iteration {$i}: value <= maxSequence");
        }
    }

    public function test_distributes_across_full_range_over_many_samples(): void
    {
        // Arrange — 12-bit sequence has 4096 values; 5000 samples should spread broadly
        $strategy = new RandomSequenceStrategy;
        $maxSequence = 4095;
        $samples = [];

        // Act
        for ($i = 0; $i < 5000; $i++) {
            $samples[] = $strategy->initialValue($maxSequence);
        }

        // Assert — at minimum half the value space is hit
        $unique = count(array_unique($samples));
        self::assertGreaterThan(2000, $unique, 'random distribution must span >2000 distinct values out of 4096');
    }

    public function test_handles_zero_max_sequence(): void
    {
        // Arrange — degenerate but legal: maxSequence=0 means single-value space {0}
        $strategy = new RandomSequenceStrategy;

        // Act + Assert
        self::assertSame(0, $strategy->initialValue(0), 'maxSequence=0 forces value=0');
    }

    public function test_rejects_negative_max_sequence(): void
    {
        // Arrange
        $strategy = new RandomSequenceStrategy;

        // Act + Assert
        $this->expectException(\InvalidArgumentException::class);
        $strategy->initialValue(-1);
    }
}
