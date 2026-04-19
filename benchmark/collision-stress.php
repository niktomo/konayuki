<?php

declare(strict_types=1);

/**
 * collision-stress.php — fork N children, each emits M IDs via APCu, then
 * the parent collects all IDs and asserts zero collisions.
 *
 * Usage: php benchmark/collision-stress.php [processes=8] [ids_per_process=10000]
 *
 * Requires ext-apcu, ext-pcntl, apc.enable_cli=1.
 */

require __DIR__.'/../vendor/autoload.php';

use Konayuki\Apcu\ApcuAtomicCounter;
use Konayuki\FileLock\FileLockWorkerIdAllocator;
use Konayuki\IdGenerator;
use Konayuki\Layout;
use Konayuki\RealTimestamp;
use Konayuki\Sequence\MonotonicSequenceStrategy;
use Konayuki\SystemClock;

if (! extension_loaded('pcntl') || ! extension_loaded('apcu') || ! ini_get('apc.enable_cli')) {
    fwrite(STDERR, "Requires pcntl + apcu + apc.enable_cli=1\n");
    exit(2);
}

$processes = (int) ($argv[1] ?? 8);
$idsPerProcess = (int) ($argv[2] ?? 10_000);
$lockDir = sys_get_temp_dir().'/konayuki-stress';
@mkdir($lockDir, 0775, true);

echo "Forking {$processes} processes × {$idsPerProcess} IDs each...\n";

$pipes = [];
$pids = [];
for ($i = 0; $i < $processes; $i++) {
    $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($pair === false) {
        fwrite(STDERR, "stream_socket_pair failed\n");
        exit(1);
    }
    $pid = pcntl_fork();
    if ($pid < 0) {
        fwrite(STDERR, "fork failed\n");
        exit(1);
    }
    if ($pid === 0) {
        fclose($pair[0]);
        $allocator = new FileLockWorkerIdAllocator($lockDir, maxWorkers: 1024);
        $generator = new IdGenerator(
            counter: new ApcuAtomicCounter,
            clock: new SystemClock,
            layout: Layout::default(),
            timestamp: new RealTimestamp,
            sequence: new MonotonicSequenceStrategy,
            workerId: $allocator->acquire(),
        );
        $buf = '';
        for ($j = 0; $j < $idsPerProcess; $j++) {
            $buf .= $generator->next()->value."\n";
        }
        fwrite($pair[1], $buf);
        fclose($pair[1]);
        exit(0);
    }
    fclose($pair[1]);
    $pipes[] = $pair[0];
    $pids[] = $pid;
}

// Drain all pipes concurrently with stream_select to avoid SO_SNDBUF deadlock —
// at high N×M the aggregate output (≈ 19 bytes × ids_per_process) exceeds the
// per-pipe send buffer, and serial reads would block child writes.
$buffers = array_fill(0, count($pipes), '');
$open = $pipes;
foreach ($open as $p) {
    stream_set_blocking($p, false);
}
while ($open !== []) {
    $read = $open;
    $write = null;
    $except = null;
    if (stream_select($read, $write, $except, 5) === false) {
        break;
    }
    foreach ($read as $p) {
        $idx = array_search($p, $pipes, true);
        $chunk = fread($p, 65536);
        if ($chunk === false || $chunk === '') {
            if (feof($p)) {
                fclose($p);
                unset($open[array_search($p, $open, true)]);
            }

            continue;
        }
        if ($idx !== false) {
            $buffers[$idx] .= $chunk;
        }
    }
}
$all = [];
foreach ($buffers as $buf) {
    foreach (explode("\n", $buf) as $line) {
        if ($line === '') {
            continue;
        }
        $all[] = (int) $line;
    }
}
foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
}

$total = count($all);
$unique = count(array_unique($all));
$dupes = $total - $unique;
echo "Generated: {$total}, unique: {$unique}, duplicates: {$dupes}\n";

// Cleanup
foreach (glob($lockDir.'/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($lockDir);
apcu_clear_cache();

if ($dupes > 0) {
    fwrite(STDERR, "FAIL: {$dupes} collisions detected!\n");
    exit(1);
}
echo "PASS: zero collisions across {$processes} processes.\n";
exit(0);
