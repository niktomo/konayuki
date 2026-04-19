# CLAUDE.md

## Project Overview

**Konayuki**（粉雪） — APCu の atomic counter を使って 63bit Snowflake ID を高速・大量に発行する PHP ライブラリ。

雪片（snowflake）が粉のように細かい粒で大量に降る、というメタファ。各 ID は一意かつ時系列順に並ぶ。

- 1 bit: 符号ビット（PHP int の正の範囲を保つため未使用）
- 41 bit: timestamp (ms since custom epoch、約 69 年)
- 10 bit: worker_id (1024 worker)
- 12 bit: sequence (4096 IDs / ms / worker)

合計 63 bit → PHP の正の int 範囲に収まる。

## Commands

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=512M
vendor/bin/pint
```

## Architecture

- `src/IdGenerator` — メイン API。`next(): SnowflakeId` で次の ID を発行
- `src/SnowflakeId` — 値オブジェクト。timestamp / workerId / sequence に分解可能
- `src/Layout` — bit 幅と epoch を保持する設定オブジェクト
- `src/AtomicCounter` — sequence をアトミックに増やすポート（interface）
- `src/Apcu/ApcuAtomicCounter` — 本番実装。`apcu_inc()` で 1 syscall アトミック
- `src/InMemory/InMemoryAtomicCounter` — テスト用同プロセス実装
- `src/Clock` — ms 単位の現在時刻を返すポート
- `src/SystemClock` — `(int) (microtime(true) * 1000)` の本番実装
- `src/Laravel/KonayukiServiceProvider` — Laravel 統合
- `src/Laravel/Facades/Konayuki` — Facade

## Design Principles

- **Hexagonal**: AtomicCounter / Clock を port として外出し、テスト容易性を確保
- **APCu first**: 単一ホスト前提で Redis 不要。将来分散化する場合は AtomicCounter の別実装を差し替える
- **`final class` + `readonly`**: 値オブジェクトは不変
- **`declare(strict_types=1)` 全ファイル必須**
