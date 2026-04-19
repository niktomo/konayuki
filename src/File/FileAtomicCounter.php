<?php

declare(strict_types=1);

namespace Konayuki\File;

use Konayuki\AtomicCounter;

/**
 * File-based atomic counter — for environments without APCu, or where APCu is reserved
 * for other consumers. Slower than ApcuAtomicCounter (~100×) but offers identical
 * correctness guarantees on a single host via flock.
 *
 * All counter state lives in one JSON file, serialized via flock(LOCK_EX). Suitable
 * for low-throughput services or as a non-APCu deployment option.
 */
final class FileAtomicCounter implements AtomicCounter
{
    private const SENTINEL_KEY = '__konayuki_alive__';

    public function __construct(public readonly string $stateFile)
    {
        $dir = dirname($stateFile);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Cannot create state directory: {$dir}");
        }
    }

    public function increment(string $key, int $ttlSeconds): int
    {
        $fp = $this->openExclusive();
        try {
            $data = $this->readData($fp);
            $now = time();
            $this->garbageCollect($data, $now);

            $current = isset($data[$key]) ? (int) $data[$key][0] : 0;
            $next = $current + 1;
            $data[$key] = [$next, $now + max(1, $ttlSeconds)];

            $this->writeData($fp, $data);

            return $next;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function wasReinitialized(): bool
    {
        $fp = $this->openExclusive();
        try {
            $data = $this->readData($fp);
            $this->garbageCollect($data, time());

            if (isset($data[self::SENTINEL_KEY])) {
                return false;
            }
            $data[self::SENTINEL_KEY] = [1, time() + 86400 * 365];
            $this->writeData($fp, $data);

            return true;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * @return resource
     */
    private function openExclusive()
    {
        $fp = @fopen($this->stateFile, 'c+b');
        if ($fp === false) {
            throw new \RuntimeException("Cannot open state file: {$this->stateFile}");
        }
        if (! flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new \RuntimeException("Cannot acquire lock on: {$this->stateFile}");
        }

        return $fp;
    }

    /**
     * @param  resource  $fp
     * @return array<string, array{0:int,1:int}>
     */
    private function readData($fp): array
    {
        rewind($fp);
        $raw = stream_get_contents($fp);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  resource  $fp
     * @param  array<string, array{0:int,1:int}>  $data
     */
    private function writeData($fp, array $data): void
    {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_THROW_ON_ERROR));
        fflush($fp);
    }

    /**
     * @param  array<string, array{0:int,1:int}>  $data
     */
    private function garbageCollect(array &$data, int $now): void
    {
        foreach ($data as $k => $entry) {
            if (! is_array($entry) || ! isset($entry[1]) || $entry[1] < $now) {
                unset($data[$k]);
            }
        }
    }
}
