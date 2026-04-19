<?php

declare(strict_types=1);

/**
 * worker-id-acquire.php — measure boot-time cost of each WorkerIdAllocator.
 *
 * acquire() is called ONCE per process at IdGenerator construction.
 * It is NOT in the hot path of next(). This benchmark exists so users
 * can reason about cold-start latency (FrankenPHP / Octane / FPM warmup).
 *
 * Usage: php benchmark/worker-id-acquire.php [iterations=1000]
 */

require __DIR__.'/../vendor/autoload.php';

use Konayuki\FileLock\FileLockWorkerIdAllocator;
use Konayuki\Fixed\FixedWorkerIdAllocator;
use Konayuki\Hint\HostnameHashWorkerIdAllocator;
use Konayuki\Hint\IpHashWorkerIdAllocator;
use Konayuki\Hint\IpLastOctetWorkerIdAllocator;
use Konayuki\WorkerIdAllocator;

$iterations = (int) ($argv[1] ?? 1_000);

$lockDir = sys_get_temp_dir().'/konayuki-bench-worker-id-'.getmypid();
@mkdir($lockDir, 0775, true);

/**
 * Construct + acquire + release, repeated $iterations times.
 *
 * @param  callable(): WorkerIdAllocator  $factory
 */
function measure(string $name, callable $factory, int $iterations): array
{
    // Warm-up
    for ($i = 0; $i < 10; $i++) {
        $a = $factory();
        $a->acquire();
        $a->release();
    }

    $samples = [];
    for ($i = 0; $i < $iterations; $i++) {
        $start = hrtime(true);
        $allocator = $factory();
        $allocator->acquire();
        $samples[] = hrtime(true) - $start;
        $allocator->release();
    }

    sort($samples);

    return [
        'name' => $name,
        'mean_ns' => array_sum($samples) / count($samples),
        'p50_ns' => $samples[(int) (count($samples) * 0.50)],
        'p99_ns' => $samples[(int) (count($samples) * 0.99)],
        'min_ns' => $samples[0],
        'max_ns' => end($samples),
    ];
}

$results = [
    measure('fixed', static fn () => new FixedWorkerIdAllocator(7), $iterations),
    measure('ip-last-octet', static fn () => new IpLastOctetWorkerIdAllocator('10.0.0.7'), $iterations),
    measure('ip-hash', static fn () => new IpHashWorkerIdAllocator('10.0.0.7'), $iterations),
    measure('hostname-hash', static fn () => new HostnameHashWorkerIdAllocator('app-7'), $iterations),
    measure('file-lock', static fn () => new FileLockWorkerIdAllocator($lockDir, 1024), $iterations),
];

printf("WorkerIdAllocator boot-time cost (acquire is called ONCE per process)\n");
printf("Iterations per allocator: %s\n", number_format($iterations));
printf("--------------------------------------------------------------------\n");
printf("%-16s %12s %12s %12s %12s %12s\n", 'allocator', 'mean', 'p50', 'p99', 'min', 'max');
printf("--------------------------------------------------------------------\n");

foreach ($results as $r) {
    $fmt = static fn (float $ns): string => $ns < 1_000
        ? sprintf('%.0f ns', $ns)
        : ($ns < 1_000_000
            ? sprintf('%.1f µs', $ns / 1_000)
            : sprintf('%.2f ms', $ns / 1_000_000));

    printf(
        "%-16s %12s %12s %12s %12s %12s\n",
        $r['name'],
        $fmt($r['mean_ns']),
        $fmt((float) $r['p50_ns']),
        $fmt((float) $r['p99_ns']),
        $fmt((float) $r['min_ns']),
        $fmt((float) $r['max_ns']),
    );
}

// Cleanup lock files
foreach (glob($lockDir.'/*.lock') ?: [] as $f) {
    @unlink($f);
}
@rmdir($lockDir);
