<?php

declare(strict_types=1);

namespace Konayuki\Tests\Feature;

use Konayuki\FileLock\FileLockWorkerIdAllocator;
use PHPUnit\Framework\TestCase;

final class FileLockWorkerIdAllocatorTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/konayuki-test-'.uniqid();
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir.'/*') ?: [];
        foreach ($files as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
    }

    public function test_first_acquire_returns_zero(): void
    {
        // Arrange
        $allocator = new FileLockWorkerIdAllocator($this->tmpDir, maxWorkers: 16);

        // Act
        $id = $allocator->acquire();

        // Assert
        self::assertSame(0, $id, 'first allocator picks worker 0');
        self::assertFileExists($this->tmpDir.'/worker-0.lock', 'lock file created');
    }

    public function test_second_concurrent_allocator_picks_one(): void
    {
        // Given — first allocator holds slot 0
        $a = new FileLockWorkerIdAllocator($this->tmpDir, maxWorkers: 16);
        $a->acquire();

        // When — second allocator acquires while first still holds
        $b = new FileLockWorkerIdAllocator($this->tmpDir, maxWorkers: 16);
        $idB = $b->acquire();

        // Then — second gets slot 1 (next free)
        self::assertSame(1, $idB, 'second allocator picks the next free slot');
    }

    public function test_acquire_is_idempotent_per_instance(): void
    {
        // Arrange
        $a = new FileLockWorkerIdAllocator($this->tmpDir, maxWorkers: 16);

        // Act
        $first = $a->acquire();
        $second = $a->acquire();

        // Assert
        self::assertSame($first, $second, 'acquire returns same value when called twice on same instance');
    }

    public function test_release_frees_slot_for_reuse(): void
    {
        // Given — allocator A holds slot 0 then releases
        $a = new FileLockWorkerIdAllocator($this->tmpDir, maxWorkers: 16);
        $a->acquire();
        $a->release();

        // When — allocator B acquires
        $b = new FileLockWorkerIdAllocator($this->tmpDir, maxWorkers: 16);
        $idB = $b->acquire();

        // Then — B reuses slot 0
        self::assertSame(0, $idB, 'released slot is reusable');
    }

    public function test_throws_when_pool_exhausted(): void
    {
        // Given — fill the pool
        $allocators = [];
        for ($i = 0; $i < 4; $i++) {
            $a = new FileLockWorkerIdAllocator($this->tmpDir, maxWorkers: 4);
            $allocators[] = $a;
            $a->acquire();
        }

        // When + Then
        $this->expectException(\RuntimeException::class);
        $overflow = new FileLockWorkerIdAllocator($this->tmpDir, maxWorkers: 4);
        $overflow->acquire();
    }
}
