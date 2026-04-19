<?php

declare(strict_types=1);

namespace Konayuki\Tests\Unit;

use Konayuki\FrozenClock;
use Konayuki\JitteredTimestamp;
use PHPUnit\Framework\TestCase;

final class JitteredTimestampTest extends TestCase
{
    public function test_output_stays_within_jitter_window(): void
    {
        // Arrange
        $clock = new FrozenClock(10_000);
        $strategy = new JitteredTimestamp(jitterMs: 50);

        // Act + Assert — sample many times, all within ±50 of (10000 - epoch)
        for ($i = 0; $i < 100; $i++) {
            $value = $strategy->compute($clock, epochMs: 0);
            self::assertGreaterThanOrEqual(9_950, $value, "JitteredTimestamp lower bound (iteration {$i})");
            self::assertLessThanOrEqual(10_050, $value, "JitteredTimestamp upper bound (iteration {$i})");
        }
    }

    public function test_distribution_spreads_across_window(): void
    {
        // Arrange
        $clock = new FrozenClock(0);
        $strategy = new JitteredTimestamp(jitterMs: 100);

        // Act
        $samples = [];
        for ($i = 0; $i < 200; $i++) {
            $samples[] = $strategy->compute($clock, epochMs: 0);
        }

        // Assert — at least 20 distinct values across 200 samples (loose check)
        self::assertGreaterThan(20, count(array_unique($samples)), 'Jitter must produce diverse values');
    }

    public function test_rejects_zero_jitter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JitteredTimestamp(0);
    }
}
