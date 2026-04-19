<?php

declare(strict_types=1);

namespace Konayuki\Hint;

use Konayuki\WorkerIdAllocator;

/**
 * Hashes the host's hostname (`gethostname()`) to a worker_id slot.
 *
 * Best for:
 *  - Kubernetes StatefulSet pods (Pod names like `app-0`, `app-1` are stable per ordinal).
 *  - On-prem clusters with stable hostnames.
 *
 * Caveats:
 *  - Containers without a stable hostname (default Docker/Kubernetes Deployment pods)
 *    get random hostnames per restart — same physical pod gets different worker_id
 *    after restart. Use {@see IpHashWorkerIdAllocator} or cloud allocators instead.
 *  - Probabilistic collision: same trade-off as IpHashWorkerIdAllocator.
 */
final class HostnameHashWorkerIdAllocator implements WorkerIdAllocator
{
    public function __construct(
        private readonly ?string $hostnameOverride = null,
        private readonly int $maxWorkers = 1024,
    ) {}

    public function acquire(): int
    {
        $hostname = $this->hostnameOverride ?? gethostname();
        if (! is_string($hostname) || $hostname === '') {
            throw new \RuntimeException('gethostname() returned empty or false');
        }

        return HintHasher::toWorkerId($hostname, $this->maxWorkers);
    }

    public function release(): void
    {
        // no-op
    }
}
