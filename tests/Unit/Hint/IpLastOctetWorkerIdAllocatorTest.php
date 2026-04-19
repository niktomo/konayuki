<?php

declare(strict_types=1);

namespace Konayuki\Tests\Unit\Hint;

use Konayuki\Hint\IpLastOctetWorkerIdAllocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IpLastOctetWorkerIdAllocatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function ipv4LastOctetCases(): iterable
    {
        yield 'first usable in /24' => ['10.0.0.1', 1];
        yield 'mid range' => ['10.0.0.42', 42];
        yield 'last usable' => ['10.0.0.254', 254];
        yield 'broadcast-like value' => ['10.0.0.255', 255];
        yield 'public IP last octet' => ['8.8.8.8', 8];
    }

    #[DataProvider('ipv4LastOctetCases')]
    public function test_extracts_last_octet_correctly(string $ip, int $expected): void
    {
        // Arrange
        $allocator = new IpLastOctetWorkerIdAllocator(ipOverride: $ip);

        // Act
        $workerId = $allocator->acquire();

        // Assert
        self::assertSame($expected, $workerId, "last octet of {$ip} must be {$expected}");
    }

    public function test_rejects_ipv6_address(): void
    {
        // Arrange
        $allocator = new IpLastOctetWorkerIdAllocator(ipOverride: '2001:db8::1');

        // Act + Assert
        $this->expectException(\RuntimeException::class);
        $allocator->acquire();
    }

    public function test_rejects_invalid_ip_string(): void
    {
        // Arrange
        $allocator = new IpLastOctetWorkerIdAllocator(ipOverride: 'not-an-ip');

        // Act + Assert
        $this->expectException(\RuntimeException::class);
        $allocator->acquire();
    }

    public function test_rejects_too_small_max_workers(): void
    {
        // last octet can be 0-255, so maxWorkers must be >= 256
        $this->expectException(\InvalidArgumentException::class);
        new IpLastOctetWorkerIdAllocator(ipOverride: '10.0.0.1', maxWorkers: 255);
    }

    public function test_release_is_noop(): void
    {
        // Arrange
        $allocator = new IpLastOctetWorkerIdAllocator(ipOverride: '10.0.0.5');

        // Act + Assert — does not throw
        $allocator->release();
        self::assertSame(5, $allocator->acquire(), 'acquire still works after release');
    }
}
