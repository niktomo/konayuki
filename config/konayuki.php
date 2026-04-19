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
    | mode: 'file-lock' | 'fixed'
    |  - file-lock: each process acquires the next free worker_id via flock
    |  - fixed:     use KONAYUKI_WORKER_ID env (caller guarantees uniqueness)
    */
    'worker_id' => [
        'mode' => env('KONAYUKI_WORKER_ID_MODE', 'file-lock'),
        'fixed_value' => env('KONAYUKI_WORKER_ID'),
        'lock_dir' => env('KONAYUKI_LOCK_DIR'),
        'max_workers' => (int) env('KONAYUKI_MAX_WORKERS', 1024),
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
