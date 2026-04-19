<?php

declare(strict_types=1);

namespace Konayuki;

final class SystemClock implements Clock
{
    public function nowMs(): int
    {
        return (int) (microtime(true) * 1000);
    }

    public function sleepMicroseconds(int $microseconds): void
    {
        usleep($microseconds);
    }
}
