<?php

declare(strict_types=1);

namespace Konayuki\Tests\Unit;

use Konayuki\Layout;
use PHPUnit\Framework\TestCase;

final class LayoutTest extends TestCase
{
    public function test_default_layout_has_63_bit_total(): void
    {
        // Arrange + Act
        $layout = Layout::default();

        // Assert
        self::assertSame(
            63,
            $layout->timestampBits + $layout->workerBits + $layout->sequenceBits,
            'Default layout must total 63 bits (1 sign bit reserved)'
        );
        self::assertSame(41, $layout->timestampBits, 'Default timestamp bits');
        self::assertSame(10, $layout->workerBits, 'Default worker bits');
        self::assertSame(12, $layout->sequenceBits, 'Default sequence bits');
    }

    public function test_bit_totals_must_equal_63(): void
    {
        // Arrange + Act + Assert
        $this->expectException(\InvalidArgumentException::class);
        new Layout(epochMs: 0, timestampBits: 42, workerBits: 10, sequenceBits: 12);
    }

    public function test_max_values_correspond_to_bit_widths(): void
    {
        // Arrange
        $layout = new Layout(epochMs: 0, timestampBits: 41, workerBits: 10, sequenceBits: 12);

        // Act + Assert
        self::assertSame((1 << 41) - 1, $layout->maxTimestamp, 'maxTimestamp = 2^41 - 1');
        self::assertSame(1023, $layout->maxWorkerId, 'maxWorkerId = 2^10 - 1');
        self::assertSame(4095, $layout->maxSequence, 'maxSequence = 2^12 - 1');
        self::assertSame(12, $layout->workerShift, 'workerShift = sequenceBits');
        self::assertSame(22, $layout->timestampShift, 'timestampShift = sequenceBits + workerBits');
    }
}
