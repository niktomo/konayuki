<?php

declare(strict_types=1);

namespace Konayuki;

interface Clock
{
    public function nowMs(): int;

    public function sleepMicroseconds(int $microseconds): void;
}
