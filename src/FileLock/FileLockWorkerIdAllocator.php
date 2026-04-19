<?php

declare(strict_types=1);

namespace Konayuki\FileLock;

use Konayuki\WorkerIdAllocator;

/**
 * Worker-id allocator that uses POSIX advisory file locks (flock).
 *
 * Each worker_id corresponds to a lock file. acquire() probes IDs starting from 0,
 * locking the first file that is unowned. The lock is automatically released by
 * the kernel when the holder's file descriptor is closed (typically on process exit).
 *
 * Safe across crashes (kernel-managed release), pipelines, supervisord numprocs,
 * and CI parallel jobs on the same host.
 */
final class FileLockWorkerIdAllocator implements WorkerIdAllocator
{
    /** @var resource|null */
    private $lockHandle = null;

    private ?int $acquiredId = null;

    public function __construct(
        public readonly string $lockDirectory,
        public readonly int $maxWorkers,
    ) {
        if ($maxWorkers < 1) {
            throw new \InvalidArgumentException('maxWorkers must be >= 1.');
        }
    }

    public function acquire(): int
    {
        if ($this->acquiredId !== null) {
            return $this->acquiredId;
        }

        if (! is_dir($this->lockDirectory) && ! @mkdir($this->lockDirectory, 0775, true) && ! is_dir($this->lockDirectory)) {
            throw new \RuntimeException("Cannot create lock directory: {$this->lockDirectory}");
        }

        for ($id = 0; $id < $this->maxWorkers; $id++) {
            $path = sprintf('%s/worker-%d.lock', rtrim($this->lockDirectory, '/'), $id);
            $handle = @fopen($path, 'cb');
            if ($handle === false) {
                continue;
            }
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                $this->lockHandle = $handle;
                $this->acquiredId = $id;

                return $id;
            }
            fclose($handle);
        }

        throw new \RuntimeException(
            "Cannot acquire worker_id: all {$this->maxWorkers} slots are in use under {$this->lockDirectory}."
        );
    }

    public function release(): void
    {
        if ($this->lockHandle !== null) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
            $this->acquiredId = null;
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
