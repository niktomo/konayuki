<?php

declare(strict_types=1);

/**
 * adapter-comparison.php — compare throughput across the three AtomicCounter
 * adapters: Apcu, File (flock + JSON), Redis (Lua INCR + EXPIRE).
 *
 * Usage: php benchmark/adapter-comparison.php [count=50000]
 *
 * Redis: requires KONAYUKI_REDIS_HOST (default 127.0.0.1) and ext-redis.
 */

require __DIR__.'/../vendor/autoload.php';

use Konayuki\Apcu\ApcuAtomicCounter;
use Konayuki\AtomicCounter;
use Konayuki\File\FileAtomicCounter;
use Konayuki\Fixed\FixedWorkerIdAllocator;
use Konayuki\IdGenerator;
use Konayuki\Layout;
use Konayuki\RealTimestamp;
use Konayuki\Redis\RedisAtomicCounter;
use Konayuki\SystemClock;

$count = (int) ($argv[1] ?? 50_000);

/**
 * @return array{ids_per_sec:int, ns_per_id:float}
 */
$run = static function (string $label, AtomicCounter $counter, int $count): array {
    $generator = new IdGenerator(
        counter: $counter,
        clock: new SystemClock,
        layout: Layout::default(),
        timestamp: new RealTimestamp,
        workerId: (new FixedWorkerIdAllocator(1))->acquire(),
    );
    // Warm-up
    for ($i = 0; $i < 200; $i++) {
        $generator->next();
    }
    $start = hrtime(true);
    for ($i = 0; $i < $count; $i++) {
        $generator->next();
    }
    $elapsedNs = hrtime(true) - $start;
    $ips = (int) ($count / ($elapsedNs / 1e9));
    $nsPerId = $elapsedNs / $count;
    printf("%-10s %10s IDs/sec   %8.0f ns/id\n", $label, number_format($ips), $nsPerId);

    return ['ids_per_sec' => $ips, 'ns_per_id' => $nsPerId];
};

echo "Adapter throughput (count={$count} per adapter)\n";
echo str_repeat('-', 60).PHP_EOL;

// 1. APCu
if (extension_loaded('apcu') && ini_get('apc.enable_cli')) {
    apcu_clear_cache();
    $run('APCu', new ApcuAtomicCounter, $count);
} else {
    echo "APCu       skipped (apcu or apc.enable_cli unavailable)\n";
}

// 2. File
$tmp = tempnam(sys_get_temp_dir(), 'konayuki-bench-');
if ($tmp !== false) {
    @unlink($tmp);
    $run('File', new FileAtomicCounter($tmp), $count);
    @unlink($tmp);
} else {
    echo "File       skipped (cannot create tempfile)\n";
}

// 3. Redis
if (extension_loaded('redis')) {
    $host = (string) (getenv('KONAYUKI_REDIS_HOST') ?: '127.0.0.1');
    $port = (int) (getenv('KONAYUKI_REDIS_PORT') ?: 6379);
    try {
        $redis = new Redis;
        $redis->connect($host, $port, 1.0);
        $redis->flushDB();
        $run('Redis', new RedisAtomicCounter($redis, keyPrefix: 'bench:'), $count);
        $redis->flushDB();
        $redis->close();
    } catch (Throwable $e) {
        echo "Redis      skipped ({$e->getMessage()})\n";
    }
} else {
    echo "Redis      skipped (ext-redis not loaded)\n";
}
