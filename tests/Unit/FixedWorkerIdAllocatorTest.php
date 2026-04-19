<?php

declare(strict_types=1);

namespace Konayuki\Tests\Unit;

use Konayuki\Fixed\FixedWorkerIdAllocator;
use PHPUnit\Framework\TestCase;

final class FixedWorkerIdAllocatorTest extends TestCase
{
    public function test_returns_configured_value(): void
    {
        // Arrange
        $allocator = new FixedWorkerIdAllocator(workerId: 42);

        // Act + Assert
        self::assertSame(42, $allocator->acquire(), 'returns the configured worker_id');
        self::assertSame(42, $allocator->acquire(), 'idempotent across calls');
    }

    public function test_release_is_a_noop(): void
    {
        // Arrange
        $allocator = new FixedWorkerIdAllocator(workerId: 1);

        // Act
        $allocator->release();

        // Assert — still returns the same value after release
        self::assertSame(1, $allocator->acquire(), 'release does not affect subsequent acquire');
    }

    public function test_rejects_negative_worker_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FixedWorkerIdAllocator(workerId: -1);
    }
}
