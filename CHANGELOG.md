# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] — 2026-04-25

### Added

- Initial package skeleton (`composer.json`, `phpstan.neon`, `pint.json`, `phpunit.xml`)
- Architecture: `IdGenerator` + `SnowflakeId` VO + `Layout` config
- Ports: `AtomicCounter`, `Clock`, `WorkerIdAllocator`, `SequenceStrategy`, `TimestampStrategy`
- Adapters: `ApcuAtomicCounter`, `InMemoryAtomicCounter`, `SystemClock`, `FrozenClock`
- Adapters: `FileLockWorkerIdAllocator`, `FixedWorkerIdAllocator`
- Adapters: `IpHashWorkerIdAllocator`, `IpLastOctetWorkerIdAllocator`, `HostnameHashWorkerIdAllocator`
- Adapters: `MonotonicSequenceStrategy`, `RandomSequenceStrategy`
- Adapters: `RedisAtomicCounter`, `FileAtomicCounter`
- `IpHashWorkerIdAllocator::fromLayout()`, `HostnameHashWorkerIdAllocator::fromLayout()`, `IpLastOctetWorkerIdAllocator::fromLayout()` — layout-aware factory methods; auto-derive `maxWorkers` from `Layout::maxWorkerId`
- Laravel integration: `KonayukiServiceProvider`, `Konayuki` facade
- README: client-side encoding examples (JSON string, C# `long.Parse`, Kasumi obfuscation)

### Changed

- `config/konayuki.php`: `max_workers` defaults to `null` (auto-derived from `layout->maxWorkerId + 1`); explicit `KONAYUKI_MAX_WORKERS` still works as a concurrency cap
- `KonayukiServiceProvider`: derives `maxWorkers` from the layout when not explicitly configured; clamps when set — changing `worker_bits` no longer silently mismatches the allocator

### Fixed

- `README`: corrected `$id->toInt()` references to `$id->value`
- Hint allocators would produce worker_ids outside the layout's range (or under-utilize worker space) when `worker_bits` was changed without updating `max_workers` — now handled automatically
