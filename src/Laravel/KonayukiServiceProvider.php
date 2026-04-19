<?php

declare(strict_types=1);

namespace Konayuki\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Konayuki\Apcu\ApcuAtomicCounter;
use Konayuki\AtomicCounter;
use Konayuki\Clock;
use Konayuki\FileLock\FileLockWorkerIdAllocator;
use Konayuki\Fixed\FixedWorkerIdAllocator;
use Konayuki\IdGenerator;
use Konayuki\JitteredTimestamp;
use Konayuki\Layout;
use Konayuki\RealTimestamp;
use Konayuki\SystemClock;
use Konayuki\TimestampStrategy;
use Konayuki\WorkerIdAllocator;

final class KonayukiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/konayuki.php', 'konayuki');

        $this->app->singleton(Layout::class, static function (Application $app): Layout {
            $cfg = (array) $app['config']->get('konayuki');

            return new Layout(
                epochMs: (int) $cfg['epoch_ms'],
                timestampBits: (int) $cfg['layout']['timestamp_bits'],
                workerBits: (int) $cfg['layout']['worker_bits'],
                sequenceBits: (int) $cfg['layout']['sequence_bits'],
            );
        });

        $this->app->singleton(Clock::class, static fn (): Clock => new SystemClock);
        $this->app->singleton(AtomicCounter::class, static fn (): AtomicCounter => new ApcuAtomicCounter);

        $this->app->singleton(TimestampStrategy::class, static function (Application $app): TimestampStrategy {
            $cfg = (array) $app['config']->get('konayuki.timestamp');

            return match ($cfg['mode']) {
                'jittered' => new JitteredTimestamp((int) $cfg['jitter_ms']),
                default => new RealTimestamp,
            };
        });

        $this->app->singleton(WorkerIdAllocator::class, static function (Application $app): WorkerIdAllocator {
            $cfg = (array) $app['config']->get('konayuki.worker_id');

            return match ($cfg['mode']) {
                'fixed' => new FixedWorkerIdAllocator((int) $cfg['fixed_value']),
                default => new FileLockWorkerIdAllocator(
                    lockDirectory: $cfg['lock_dir'] ?? storage_path('konayuki'),
                    maxWorkers: (int) $cfg['max_workers'],
                ),
            };
        });

        $this->app->singleton(IdGenerator::class, static function (Application $app): IdGenerator {
            $allocator = $app->make(WorkerIdAllocator::class);

            return new IdGenerator(
                counter: $app->make(AtomicCounter::class),
                clock: $app->make(Clock::class),
                layout: $app->make(Layout::class),
                timestamp: $app->make(TimestampStrategy::class),
                workerId: $allocator->acquire(),
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
