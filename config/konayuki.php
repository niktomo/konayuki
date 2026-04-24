<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Epoch
    |--------------------------------------------------------------------------
    | Milliseconds since Unix epoch. All Konayuki timestamps are relative.
    | Default: 2026-01-01 00:00:00 UTC.
    */
    'epoch_ms' => (int) env('KONAYUKI_EPOCH_MS', 1_767_225_600_000),

    /*
    |--------------------------------------------------------------------------
    | Bit layout
    |--------------------------------------------------------------------------
    | Sum of timestamp_bits + worker_bits + sequence_bits must equal 63.
    */
    'layout' => [
        'timestamp_bits' => 41,
        'worker_bits' => 10,
        'sequence_bits' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker-id allocation
    |--------------------------------------------------------------------------
    | mode (pick one for your deployment shape):
    |
    |  'file-lock'      single host, multi-process (default)
    |                   → kernel-released flock per process, zero collision
    |
    |  'fixed'          deterministic from KONAYUKI_WORKER_ID env
    |                   → caller guarantees uniqueness (e.g. orchestrator-injected)
    |
    |  'ip-last-octet'  multi-host, **single /24 subnet only**
    |                   → uses last octet of primary IP, deterministic, human-readable
    |                   → DANGER: collides across subnets (10.0.0.17 vs 10.0.1.17)
    |
    |  'ip-hash'        multi-host, multi-subnet (probabilistic)
    |                   → crc32(primary IP) % max_workers
    |                   → collision probability ≈ N²/(2·max_workers); ~3% at 8 hosts
    |
    |  'hostname-hash'  Kubernetes StatefulSet, on-prem with stable hostnames
    |                   → crc32(gethostname()) % max_workers
    |                   → DANGER: random hostnames (Docker default) → unstable worker_id
    |
    | See README "Choosing a worker_id strategy" for a flowchart.
    */
    'worker_id' => [
        'mode' => env('KONAYUKI_WORKER_ID_MODE', 'file-lock'),
        'fixed_value' => env('KONAYUKI_WORKER_ID'),
        'lock_dir' => env('KONAYUKI_LOCK_DIR'),
        // Upper bound for worker_id slot allocation.
        // Defaults to 2^worker_bits (i.e. all slots). Set lower to cap concurrency.
        // Must be <= 2^worker_bits; the ServiceProvider clamps it automatically.
        'max_workers' => env('KONAYUKI_MAX_WORKERS') !== null ? (int) env('KONAYUKI_MAX_WORKERS') : null,
        // Optional override for ip-* and hostname-hash modes (testing / fixed-IP setups)
        'ip_override' => env('KONAYUKI_IP_OVERRIDE'),
        'hostname_override' => env('KONAYUKI_HOSTNAME_OVERRIDE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sequence start strategy
    |--------------------------------------------------------------------------
    | mode: 'monotonic' | 'random'
    |  - monotonic: each ms window starts sequence at 0 (production default,
    |               preserves k-sortable ordering, smallest IDs)
    |  - random:    each ms window starts sequence at random_int(0, maxSequence)
    |               → spreads IDs across DB shards / hash buckets when traffic
    |                 is too sparse to fill ms windows naturally.
    |               → still uniqueness-safe: (workerId, ms, sequence) tuple is unique.
    |               → trade-off: weakens *intra-ms* monotonic ordering only;
    |                 inter-ms ordering is preserved by the timestamp prefix.
    */
    'sequence' => [
        'mode' => env('KONAYUKI_SEQUENCE_MODE', 'monotonic'),
    ],
];
