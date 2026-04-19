<?php

declare(strict_types=1);

namespace Konayuki\Fixed;

use Konayuki\WorkerIdAllocator;

/**
 * Worker-id allocator that returns a fixed, externally assigned value.
 *
 * Use only when worker_id uniqueness is guaranteed by an external mechanism (orchestrator
 * env, single-process setup, tests). Otherwise prefer FileLockWorkerIdAllocator.
 */
final class FixedWorkerIdAllocator implements WorkerIdAllocator
{
    public function __construct(public readonly int $workerId)
    {
        if ($workerId < 0) {
            throw new \InvalidArgumentException('workerId must be >= 0.');
        }
    }

    public function acquire(): int
    {
        return $this->workerId;
    }

    public function release(): void
    {
        // no-op
    }
}
