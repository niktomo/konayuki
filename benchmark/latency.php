<?php

declare(strict_types=1);

/**
 * latency.php — measure per-call latency distribution (p50 / p95 / p99 / max)
 * for ApcuAtomicCounter-backed IdGenerator.
 *
 * Usage: php benchmark/latency.php [samples=100000]
 *
 * Note: percentiles include the cost of hrtime() itself (~50 ns), so absolute
 * floor is hrtime resolution + apcu_inc syscall overhead.
 */

require __DIR__.'/../vendor/autoload.php';

use Konayuki\Apcu\ApcuAtomicCounter;
use Konayuki\Fixed\FixedWorkerIdAllocator;
use Konayuki\IdGenerator;
use Konayuki\Layout;
use Konayuki\RealTimestamp;
use Konayuki\Sequence\MonotonicSequenceStrategy;
use Konayuki\SystemClock;

if (! extension_loaded('apcu') || ! ini_get('apc.enable_cli')) {
    fwrite(STDERR, "Requires apcu + apc.enable_cli=1\n");
    exit(2);
}

$samples = (int) ($argv[1] ?? 100_000);

// Reset state BEFORE warm-up so the warm-up itself absorbs the wipe-detection cost
// (otherwise the first measured call sees wasReinitialized=true and waits ~1 ms,
// inflating max/p999 by 6 orders of magnitude).
apcu_clear_cache();

$generator = new IdGenerator(
    counter: new ApcuAtomicCounter,
    clock: new SystemClock,
    layout: Layout::default(),
    timestamp: new RealTimestamp,
    sequence: new MonotonicSequenceStrategy,
    workerId: (new FixedWorkerIdAllocator(1))->acquire(),
);

// Warm-up — JIT, opcode cache, APCu key creation
for ($i = 0; $i < 5_000; $i++) {
    $generator->next();
}

/** @var list<int> $latencies (nanoseconds per call) */
$latencies = [];
for ($i = 0; $i < $samples; $i++) {
    $t0 = hrtime(true);
    $generator->next();
    $latencies[] = hrtime(true) - $t0;
}

sort($latencies);
$pick = static fn (float $pct): int => $latencies[(int) floor(($samples - 1) * $pct)];

$mean = (int) (array_sum($latencies) / $samples);

printf("Samples:    %s\n", number_format($samples));
printf("min:        %d ns\n", $latencies[0]);
printf("p50:        %d ns\n", $pick(0.50));
printf("p90:        %d ns\n", $pick(0.90));
printf("p95:        %d ns\n", $pick(0.95));
printf("p99:        %d ns\n", $pick(0.99));
printf("p999:       %d ns\n", $pick(0.999));
printf("max:        %d ns\n", end($latencies));
printf("mean:       %d ns\n", $mean);

apcu_clear_cache();
