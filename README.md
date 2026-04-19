# Konayuki

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

> 粉雪 (konayuki) — *powdery snow*. Each flake is tiny, unique, and falls in vast quantities.

High-throughput **63-bit Snowflake ID generator** backed by **APCu shared memory**. Designed for single-host PHP applications that need:

- ⚡ Lock-free, in-process atomic sequence (no Redis, no Zookeeper)
- 📈 Up to ~4 million IDs / second / worker
- 🕒 Time-ordered, k-sortable IDs (timestamp prefix)
- 🔒 63-bit fits inside PHP's signed int — safe for JSON, MySQL `BIGINT`, etc.

## Layout

```
| 1 bit unused | 41 bits timestamp (ms) | 10 bits worker_id | 12 bits sequence |
```

- **timestamp**: ~69 years from custom epoch
- **worker_id**: 1024 workers (single host = 1, multi-host scale-out via env)
- **sequence**: 4096 IDs / ms / worker

## Install

```bash
composer require niktomo/konayuki
```

Requires `ext-apcu`. On Laravel, the service provider is auto-discovered.

## Usage

```php
use Konayuki\IdGenerator;

$id = $generator->next();          // SnowflakeId
$id->toInt();                      // 7,123,456,789,012,345
$id->timestamp();                  // 1,712,345,678,000 (ms)
$id->workerId();                   // 1
$id->sequence();                   // 42
```

### Laravel

```php
use Konayuki\Laravel\Facades\Konayuki;

$id = Konayuki::next();
```

### Testing

Swap `AtomicCounter` for the in-memory implementation in tests:

```php
use Konayuki\InMemory\InMemoryAtomicCounter;
use Konayuki\IdGenerator;
use Konayuki\Layout;

$generator = new IdGenerator(
    counter:  new InMemoryAtomicCounter(),
    clock:    new FrozenClock(1_712_345_678_000),
    layout:   Layout::default(),
    workerId: 1,
);
```

## Design

- **Hexagonal**: `AtomicCounter`, `Clock`, `TimestampStrategy`, and `WorkerIdAllocator` are ports. APCu is one adapter; alternatives can be added without touching `IdGenerator`.
- **No globals**: `IdGenerator` is a regular DI-friendly class.
- **Sign-bit safe**: outputs always fit in PHP signed `int` (max 9.2 × 10^18).

## Operational guide

### APCu sizing (`apc.shm_size`)

Konayuki itself uses very little APCu (≪ 2 MB at typical load). The right `shm_size`
depends on what else lives in the same APCu segment:

| co-tenant | recommended `apc.shm_size` |
|---|---|
| Konayuki only | **32 MB** (default) |
| + Laravel `cache.driver=apc` | 128 MB |
| + game-style master data (50–500 MB) | master size × 2 |
| + heavy MMO master data (1 GB+) | 4 GB+ |

Set in `php.ini`:

```ini
apc.shm_size = 128M
apc.enable_cli = 1
```

### Worker-id allocation across processes

Konayuki's atomic guarantees hold within one APCu segment (= one PHP master process tree:
FrankenPHP/Octane workers, FPM workers under one pool). **Separately launched processes**
(`queue:work`, `artisan` CLI, separate cron jobs) get **separate APCu segments** and could
collide unless they each hold a unique `worker_id`.

The default `FileLockWorkerIdAllocator` solves this by having each process atomically claim
the next free `worker_id` via `flock` at boot. The kernel releases the lock automatically
when the process exits, so crashes and `kill -9` cannot leak slots.

```php
use Konayuki\FileLock\FileLockWorkerIdAllocator;

$allocator = new FileLockWorkerIdAllocator(
    lockDirectory: storage_path('konayuki'),
    maxWorkers: 1024,
);
$workerId = $allocator->acquire(); // unique per concurrent process on this host
```

### Local-dev shard distribution (`JitteredTimestamp`)

In low-traffic local development, every request may hit the same millisecond, causing IDs
to cluster on the same shard if your shard key is timestamp-derived. Enable jitter mode for
local environments only:

```php
'timestamp' => [
    'mode'      => 'jittered',
    'jitter_ms' => 100,  // ±100 ms random offset
],
```

This breaks k-sortable ordering — never enable in production.

### Diagnose with `konayuki-doctor`

```bash
vendor/bin/konayuki-doctor
```

Checks PHP version, APCu extension, locking type, `apcu_inc` correctness, available
APCu memory, and `flock` support. Exit 0 = healthy, 1 = warnings, 2 = unsafe.

## License

MIT
