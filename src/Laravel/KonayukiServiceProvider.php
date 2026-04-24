<?php

declare(strict_types=1);

namespace Konayuki\Laravel;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Konayuki\Apcu\ApcuAtomicCounter;
use Konayuki\AtomicCounter;
use Konayuki\Clock;
use Konayuki\FileLock\FileLockWorkerIdAllocator;
use Konayuki\Fixed\FixedWorkerIdAllocator;
use Konayuki\Hint\HostnameHashWorkerIdAllocator;
use Konayuki\Hint\IpHashWorkerIdAllocator;
use Konayuki\Hint\IpLastOctetWorkerIdAllocator;
use Konayuki\IdGenerator;
use Konayuki\Layout;
use Konayuki\RealTimestamp;
use Konayuki\Sequence\MonotonicSequenceStrategy;
use Konayuki\Sequence\RandomSequenceStrategy;
use Konayuki\SequenceStrategy;
use Konayuki\SystemClock;
use Konayuki\TimestampStrategy;
use Konayuki\WorkerIdAllocator;

final class KonayukiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/konayuki.php', 'konayuki');

        $this->app->singleton(Layout::class, static function (Application $app): Layout {
            $cfg = (array) $app->make(ConfigRepository::class)->get('konayuki');

            return new Layout(
                epochMs: (int) $cfg['epoch_ms'],
                timestampBits: (int) $cfg['layout']['timestamp_bits'],
                workerBits: (int) $cfg['layout']['worker_bits'],
                sequenceBits: (int) $cfg['layout']['sequence_bits'],
            );
        });

        $this->app->singleton(Clock::class, static fn (): Clock => new SystemClock);
        $this->app->singleton(AtomicCounter::class, static function (Application $app): AtomicCounter {
            $prefix = (string) $app->make(ConfigRepository::class)->get('konayuki.key_prefix', IdGenerator::DEFAULT_KEY_PREFIX);

            return new ApcuAtomicCounter($prefix);
        });
        $this->app->singleton(TimestampStrategy::class, static fn (): TimestampStrategy => new RealTimestamp);

        $this->app->singleton(SequenceStrategy::class, static function (Application $app): SequenceStrategy {
            $cfg = (array) $app->make(ConfigRepository::class)->get('konayuki.sequence');

            return match ($cfg['mode'] ?? 'monotonic') {
                'random' => new RandomSequenceStrategy,
                default => new MonotonicSequenceStrategy,
            };
        });

        $this->app->singleton(WorkerIdAllocator::class, static function (Application $app): WorkerIdAllocator {
            $cfg = (array) $app->make(ConfigRepository::class)->get('konayuki.worker_id');
            $maxWorkers = (int) $cfg['max_workers'];
            $ipOverride = is_string($cfg['ip_override'] ?? null) ? $cfg['ip_override'] : null;
            $hostnameOverride = is_string($cfg['hostname_override'] ?? null) ? $cfg['hostname_override'] : null;

            return match ($cfg['mode']) {
                'fixed' => new FixedWorkerIdAllocator((int) $cfg['fixed_value']),
                'ip-last-octet' => new IpLastOctetWorkerIdAllocator($ipOverride, $maxWorkers),
                'ip-hash' => new IpHashWorkerIdAllocator($ipOverride, $maxWorkers),
                'hostname-hash' => new HostnameHashWorkerIdAllocator($hostnameOverride, $maxWorkers),
                default => new FileLockWorkerIdAllocator(
                    lockDirectory: is_string($cfg['lock_dir'] ?? null) ? $cfg['lock_dir'] : storage_path('konayuki'),
                    maxWorkers: $maxWorkers,
                ),
            };
        });

        $this->app->singleton(IdGenerator::class, static function (Application $app): IdGenerator {
            $allocator = $app->make(WorkerIdAllocator::class);
            $prefix = (string) $app->make(ConfigRepository::class)->get('konayuki.key_prefix', IdGenerator::DEFAULT_KEY_PREFIX);

            return new IdGenerator(
                counter: $app->make(AtomicCounter::class),
                clock: $app->make(Clock::class),
                layout: $app->make(Layout::class),
                timestamp: $app->make(TimestampStrategy::class),
                sequence: $app->make(SequenceStrategy::class),
                workerId: $allocator->acquire(),
                keyPrefix: $prefix,
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/konayuki.php' => config_path('konayuki.php'),
            ], 'konayuki-config');
        }
    }
}
