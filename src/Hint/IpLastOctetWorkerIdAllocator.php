<?php

declare(strict_types=1);

namespace Konayuki\Hint;

use Konayuki\Layout;
use Konayuki\WorkerIdAllocator;

/**
 * Uses the last octet of the host's IPv4 address as worker_id.
 *
 * REQUIRED PRECONDITION:
 *  - All hosts in the deployment share the same /24 subnet (or smaller).
 *    Otherwise `10.0.0.17` and `10.0.1.17` collide on worker_id=17 → ID collision.
 *
 * Best for: small on-prem clusters, single AWS VPC private subnet, single GCP subnet.
 * Bad for:  multi-AZ deployments, Kubernetes pod networks (typically /16), IPv6.
 *
 * For multi-subnet deployments, use {@see IpHashWorkerIdAllocator} or
 * {@see HostnameHashWorkerIdAllocator} instead.
 */
final class IpLastOctetWorkerIdAllocator implements WorkerIdAllocator
{
    public function __construct(
        private readonly ?string $ipOverride = null,
        int $maxWorkers = 1024,
    ) {
        if ($maxWorkers < 256) {
            throw new \InvalidArgumentException(
                "maxWorkers must be >= 256 to fit a full IPv4 last octet, got {$maxWorkers}"
            );
        }
    }

    public static function fromLayout(Layout $layout, ?string $ipOverride = null): self
    {
        if ($layout->maxWorkerId < 255) {
            throw new \InvalidArgumentException(
                "Layout workerBits must be >= 8 for IpLastOctetWorkerIdAllocator (maxWorkerId={$layout->maxWorkerId} < 255)"
            );
        }

        return new self($ipOverride, $layout->maxWorkerId + 1);
    }

    public function acquire(): int
    {
        $ip = $this->ipOverride ?? IpResolver::primaryIpv4();
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new \RuntimeException("Cannot derive worker_id from non-IPv4 address: {$ip}");
        }
        $parts = explode('.', $ip);

        return (int) $parts[3];
    }

    public function release(): void
    {
        // no-op — deterministic allocation holds no resources
    }
}
