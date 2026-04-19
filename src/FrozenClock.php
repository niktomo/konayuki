<?php

declare(strict_types=1);

namespace Konayuki;

final class FrozenClock implements Clock
{
    public function __construct(private int $nowMs) {}

    public function nowMs(): int
    {
        return $this->nowMs;
    }

    public function sleepMicroseconds(int $microseconds): void
    {
        $this->nowMs += (int) ceil($microseconds / 1000);
    }

    public function advance(int $ms): void
    {
        $this->nowMs += $ms;
    }
}
