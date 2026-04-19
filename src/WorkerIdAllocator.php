<?php

declare(strict_types=1);

namespace Konayuki;

interface WorkerIdAllocator
{
    /**
     * Reserve a unique worker_id within the host. Holds the reservation for the
     * lifetime of the holder; OS releases it on process death.
     *
     * @throws \RuntimeException when no worker_id is available within the configured pool.
     */
    public function acquire(): int;

    /**
     * Release the reservation early (optional — OS auto-releases on process exit).
     */
    public function release(): void;
}
