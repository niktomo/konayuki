<?php

declare(strict_types=1);

namespace Konayuki\Hint;

/**
 * Maps a host-fingerprint string (IP, hostname, instance-id) to a worker_id slot.
 *
 * crc32 is used purely for avalanche / distribution — not for security. Output is
 * deterministic per (hint, maxWorkers) pair so the same machine always gets the
 * same worker_id across restarts.
 */
final class HintHasher
{
    public static function toWorkerId(string $hint, int $maxWorkers): int
    {
        if ($maxWorkers < 1) {
            throw new \InvalidArgumentException("maxWorkers must be >= 1, got {$maxWorkers}");
        }
        if ($hint === '') {
            throw new \InvalidArgumentException('hint must be a non-empty string');
        }

        return crc32($hint) % $maxWorkers;
    }
}
