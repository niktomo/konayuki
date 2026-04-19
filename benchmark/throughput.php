<?php

declare(strict_types=1);

/**
 * throughput.php — measure single-process IDs/sec for ApcuAtomicCounter.
 *
 * Usage: php benchmark/throughput.php [count=1000000]
 */

require __DIR__.'/../vendor/autoload.php';

use Konayuki\Apcu\ApcuAtomicCounter;
use Konayuki\Fixed\FixedWorkerIdAllocator;
use Konayuki\IdGenerator;
use Konayuki\Layout;
use Konayuki\RealTimestamp;
use Konayuki\SystemClock;

if (! extension_loaded('apcu') || ! ini_get('apc.enable_cli')) {
    fwrite(STDERR, "Requires apcu + apc.enable_cli=1\n");
    exit(2);
}

$count = (int) ($argv[1] ?? 1_000_000);

$generator = new IdGenerator(
    counter: new ApcuAtomicCounter,
    clock: new SystemClock,
    layout: Layout::default(),
    timestamp: new RealTimestamp,
    workerId: (new FixedWorkerIdAllocator(1))->acquire(),
);

// Warm-up — JIT, opcode cache, APCu mutex
for ($i = 0; $i < 1_000; $i++) {
    $generator->next();
}
apcu_clear_cache();

$start = hrtime(true);
for ($i = 0; $i < $count; $i++) {
    $generator->next();
}
$elapsedNs = hrtime(true) - $start;

$elapsedSec = $elapsedNs / 1e9;
$idsPerSec = $count / $elapsedSec;
$nsPerId = $elapsedNs / $count;

printf("Generated:  %s IDs\n", number_format($count));
printf("Elapsed:    %.3f s\n", $elapsedSec);
printf("Throughput: %s IDs/sec\n", number_format((int) $idsPerSec));
printf("Per ID:     %.0f ns\n", $nsPerId);

apcu_clear_cache();
