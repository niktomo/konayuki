<?php

declare(strict_types=1);

namespace Konayuki\Tests\Unit;

use Konayuki\Layout;
use Konayuki\SnowflakeId;
use PHPUnit\Framework\TestCase;

final class SnowflakeIdTest extends TestCase
{
    public function test_compose_and_decompose_roundtrip(): void
    {
        // Arrange
        $layout = Layout::default();

        // Act
        $id = SnowflakeId::compose(
            relativeTimestamp: 123_456_789,
            workerId: 42,
            sequence: 7,
            layout: $layout,
        );

        // Assert
        self::assertSame(123_456_789 + $layout->epochMs, $id->timestamp(), 'timestamp roundtrips');
        self::assertSame(42, $id->workerId(), 'workerId roundtrips');
        self::assertSame(7, $id->sequence(), 'sequence roundtrips');
    }

    public function test_fromint_preserves_value(): void
    {
        // Arrange
        $layout = Layout::default();
        $original = SnowflakeId::compose(100, 5, 3, $layout);

        // Act
        $rebuilt = SnowflakeId::fromInt($original->value, $layout);

        // Assert
        self::assertSame($original->value, $rebuilt->value, 'value roundtrips through int');
        self::assertSame($original->timestamp(), $rebuilt->timestamp(), 'timestamp unchanged');
        self::assertSame($original->workerId(), $rebuilt->workerId(), 'workerId unchanged');
        self::assertSame($original->sequence(), $rebuilt->sequence(), 'sequence unchanged');
    }

    public function test_rejects_out_of_range_timestamp(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SnowflakeId::compose(-1, 0, 0, Layout::default());
    }

    public function test_rejects_out_of_range_worker_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SnowflakeId::compose(0, 1024, 0, Layout::default());
    }

    public function test_rejects_out_of_range_sequence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SnowflakeId::compose(0, 0, 4096, Layout::default());
    }

    public function test_value_is_always_non_negative_63bit_int(): void
    {
        // Arrange
        $layout = Layout::default();
        $id = SnowflakeId::compose($layout->maxTimestamp, $layout->maxWorkerId, $layout->maxSequence, $layout);

        // Act + Assert
        self::assertGreaterThanOrEqual(0, $id->value, 'value must be non-negative');
        self::assertLessThanOrEqual(PHP_INT_MAX, $id->value, 'value must fit PHP int');
    }

    public function test_shardkey_uses_low_bits_not_timestamp(): void
    {
        // Given two IDs issued in the same ms with different workerIds/sequences,
        // shardKey() must distribute them to different shards rather than clumping
        // on timestamp.
        $layout = Layout::default();
        $idA = SnowflakeId::compose(100, 0, 0, $layout);
        $idB = SnowflakeId::compose(100, 1, 0, $layout);
        $idC = SnowflakeId::compose(100, 0, 1, $layout);

        $keyA = $idA->shardKey(16);
        $keyB = $idB->shardKey(16);
        $keyC = $idC->shardKey(16);

        // They should differ at least pairwise (low-bit entropy)
        self::assertNotSame($keyA, $keyB, 'Different worker should shard differently in same ms');
        self::assertNotSame($keyA, $keyC, 'Different sequence should shard differently in same ms');
    }
}
