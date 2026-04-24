<?php

declare(strict_types=1);

namespace Konayuki\Tests\Unit\Hint;

use Konayuki\Hint\HostnameHashWorkerIdAllocator;
use Konayuki\Layout;
use PHPUnit\Framework\TestCase;

final class HostnameHashWorkerIdAllocatorTest extends TestCase
{
    public function test_returns_value_within_max_workers(): void
    {
        // Arrange
        $allocator = new HostnameHashWorkerIdAllocator(hostnameOverride: 'app-7.svc.cluster.local', maxWorkers: 1024);

        // Act
        $id = $allocator->acquire();

        // Assert
        self::assertGreaterThanOrEqual(0, $id, 'worker_id must be >= 0');
        self::assertLessThan(1024, $id, 'worker_id must be < maxWorkers');
    }

    public function test_is_deterministic_per_hostname(): void
    {
        // Arrange — two allocators with same hostname
        $a = new HostnameHashWorkerIdAllocator(hostnameOverride: 'web-3');
        $b = new HostnameHashWorkerIdAllocator(hostnameOverride: 'web-3');

        // Assert
        self::assertSame($a->acquire(), $b->acquire(), 'same hostname must give same worker_id');
    }

    public function test_statefulset_pod_names_distribute_well(): void
    {
        // Arrange — typical k8s StatefulSet pod ordinals
        $workers = [];
        for ($i = 0; $i < 10; $i++) {
            $workers[] = (new HostnameHashWorkerIdAllocator(hostnameOverride: "app-{$i}", maxWorkers: 1024))->acquire();
        }

        // Assert — at least 8 of 10 distinct
        $unique = count(array_unique($workers));
        self::assertGreaterThanOrEqual(8, $unique, 'StatefulSet pod names should hash to distinct workers');
    }

    public function test_from_layout_constrains_to_layout_max_workers(): void
    {
        // Arrange — 8-bit worker space (255 workers)
        $layout = new Layout(epochMs: 0, timestampBits: 41, workerBits: 8, sequenceBits: 14);
        $allocator = HostnameHashWorkerIdAllocator::fromLayout($layout, hostnameOverride: 'app-42');

        // Act
        $id = $allocator->acquire();

        // Assert
        self::assertGreaterThanOrEqual(0, $id, 'worker_id must be >= 0');
        self::assertLessThanOrEqual($layout->maxWorkerId, $id, 'worker_id must not exceed layout max');
    }

    public function test_from_layout_matches_manual_construction(): void
    {
        // Arrange
        $layout = Layout::default();
        $hostname = 'worker-node-7';

        $via_factory = HostnameHashWorkerIdAllocator::fromLayout($layout, hostnameOverride: $hostname);
        $manually = new HostnameHashWorkerIdAllocator(hostnameOverride: $hostname, maxWorkers: $layout->maxWorkerId + 1);

        // Assert
        self::assertSame($via_factory->acquire(), $manually->acquire(), 'fromLayout matches manual construction');
    }

    public function test_uses_real_gethostname_when_no_override(): void
    {
        // Arrange — no override → uses gethostname() under the hood
        $allocator = new HostnameHashWorkerIdAllocator(maxWorkers: 1024);

        // Act
        $id = $allocator->acquire();

        // Assert — should not throw, should be in range
        self::assertGreaterThanOrEqual(0, $id, 'real hostname yields valid worker_id');
        self::assertLessThan(1024, $id, 'real hostname yields valid worker_id');
    }
}
