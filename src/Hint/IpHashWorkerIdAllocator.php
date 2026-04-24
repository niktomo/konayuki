<?php

declare(strict_types=1);

namespace Konayuki\Hint;

use Konayuki\Layout;
use Konayuki\WorkerIdAllocator;

/**
 * Hashes the host's primary IP address (IPv4 or IPv6) to a worker_id slot.
 *
 * Trade-offs:
 *  - Works across multiple subnets (unlike {@see IpLastOctetWorkerIdAllocator}).
 *  - Probabilistic collision: birthday-paradox ≈ 1/maxWorkers per host pair.
 *    With maxWorkers=1024 and 32 hosts, P(collision) ≈ 47%; with 8 hosts, ≈ 3%.
 *  - For larger deployments, prefer hostname-based or cloud-metadata allocators.
 */
final class IpHashWorkerIdAllocator implements WorkerIdAllocator
{
    public function __construct(
        private readonly ?string $ipOverride = null,
        private readonly int $maxWorkers = 1024,
    ) {}

    public static function fromLayout(Layout $layout, ?string $ipOverride = null): self
    {
        return new self($ipOverride, $layout->maxWorkerId + 1);
    }

    public function acquire(): int
    {
        $ip = $this->ipOverride ?? IpResolver::primaryIp();

        return HintHasher::toWorkerId($ip, $this->maxWorkers);
    }

    public function release(): void
    {
        // no-op
    }
}
