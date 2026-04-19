<?php

declare(strict_types=1);

namespace Konayuki\InMemory;

use Konayuki\AtomicCounter;

final class InMemoryAtomicCounter implements AtomicCounter
{
    /** @var array<string, int> */
    private array $store = [];

    private bool $reinitConsumed = false;

    public function increment(string $key, int $ttlSeconds): int
    {
        unset($ttlSeconds);
        $this->store[$key] = ($this->store[$key] ?? 0) + 1;

        return $this->store[$key];
    }

    public function wasReinitialized(): bool
    {
        if ($this->reinitConsumed) {
            return false;
        }
        $this->reinitConsumed = true;

        return true;
    }

    public function clear(): void
    {
        $this->store = [];
        $this->reinitConsumed = false;
    }
}
