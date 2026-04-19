<?php

declare(strict_types=1);

namespace Konayuki\Tests\Unit\Hint;

use Konayuki\Hint\IpHashWorkerIdAllocator;
use PHPUnit\Framework\TestCase;

final class IpHashWorkerIdAllocatorTest extends TestCase
{
    public function test_returns_value_within_max_workers(): void
    {
        // Arrange
        $allocator = new IpHashWorkerIdAllocator(ipOverride: '10.0.5.42', maxWorkers: 1024);

        // Act
        $id = $allocator->acquire();

        // Assert
        self::assertGreaterThanOrEqual(0, $id, 'worker_id must be >= 0');
        self::assertLessThan(1024, $id, 'worker_id must be < maxWorkers');
    }

    public function test_is_deterministic_for_same_ip(): void
    {
        // Arrange
        $a = new IpHashWorkerIdAllocator(ipOverride: '192.168.1.10');
        $b = new IpHashWorkerIdAllocator(ipOverride: '192.168.1.10');

        // Act + Assert
        self::assertSame($a->acquire(), $b->acquire(), 'same IP must yield same worker_id');
    }

    public function test_handles_ipv6_address(): void
    {
        // Arrange — IpHash does not require IPv4 (unlike IpLastOctet)
        $allocator = new IpHashWorkerIdAllocator(ipOverride: '2001:db8::1', maxWorkers: 1024);

        // Act
        $id = $allocator->acquire();

        // Assert
        self::assertGreaterThanOrEqual(0, $id, 'IPv6 hash falls in valid range');
        self::assertLessThan(1024, $id, 'IPv6 hash falls in valid range');
    }

    public function test_typically_distributes_adjacent_subnet_ips(): void
    {
        // Arrange — 16 adjacent IPs in same /28 should distribute non-trivially
        $workers = [];
        for ($i = 1; $i <= 16; $i++) {
            $workers[] = (new IpHashWorkerIdAllocator(ipOverride: "10.0.0.{$i}", maxWorkers: 1024))->acquire();
        }

        // Assert — at least some variation (crc32 avalanche should give >= 10 distinct of 16)
        $unique = count(array_unique($workers));
        self::assertGreaterThanOrEqual(10, $unique, 'crc32 avalanche must spread adjacent IPs');
    }
}
