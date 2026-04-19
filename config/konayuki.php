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
        'max_workers' => (int) env('KONAYUKI_MAX_WORKERS', 1024),
        // Optional override for ip-* and hostname-hash modes (testing / fixed-IP setups)
        'ip_override' => env('KONAYUKI_IP_OVERRIDE'),
        'hostname_override' => env('KONAYUKI_HOSTNAME_OVERRIDE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timestamp strategy
    |--------------------------------------------------------------------------
    | mode: 'real' | 'jittered'
    |  - real:     wall-clock ms (production)
    |  - jittered: wall-clock ms ± random(0..jitter_ms) — **local dev only**.
    |              Breaks k-sortable ordering; intended for spreading IDs across
    |              shards when traffic is too sparse to fill ms windows.
    */
    'timestamp' => [
        'mode' => env('KONAYUKI_TIMESTAMP_MODE', 'real'),
        'jitter_ms' => (int) env('KONAYUKI_JITTER_MS', 100),
    ],
];
