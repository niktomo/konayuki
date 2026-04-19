<?php

declare(strict_types=1);

namespace Konayuki\Tests\Unit\Hint;

use Konayuki\Hint\HintHasher;
use PHPUnit\Framework\TestCase;

final class HintHasherTest extends TestCase
{
    public function test_returns_value_within_range(): void
    {
        // Arrange
        $maxWorkers = 1024;

        // Act
        $id = HintHasher::toWorkerId('10.0.0.42', $maxWorkers);

        // Assert
        self::assertGreaterThanOrEqual(0, $id, 'worker_id must be >= 0');
        self::assertLessThan($maxWorkers, $id, 'worker_id must be < maxWorkers');
    }

    public function test_is_deterministic_for_same_input(): void
    {
        // Act
        $a = HintHasher::toWorkerId('host-7.example', 1024);
        $b = HintHasher::toWorkerId('host-7.example', 1024);

        // Assert
        self::assertSame($a, $b, 'same hint must produce same worker_id across calls');
    }

    public function test_different_hints_typically_produce_different_ids(): void
    {
        // Arrange — two hints that differ only by one character
        $a = HintHasher::toWorkerId('host-1', 1024);
        $b = HintHasher::toWorkerId('host-2', 1024);

        // Assert — crc32 is not collision-free, but adjacent strings should differ
        self::assertNotSame($a, $b, 'adjacent hints should hash to different worker_ids');
    }

    public function test_rejects_empty_hint(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HintHasher::toWorkerId('', 1024);
    }

    public function test_rejects_zero_max_workers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HintHasher::toWorkerId('host', 0);
    }
}
