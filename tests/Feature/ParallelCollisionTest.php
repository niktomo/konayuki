<?php

declare(strict_types=1);

namespace Konayuki\Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Forks N child processes via proc_open(php), each emits M IDs via APCu, then the
 * parent collects all IDs and asserts zero collisions. This is the user-visible
 * proof that the (workerId, ms, sequence) tuple is unique under real concurrency,
 * not just under simulated time in unit tests.
 *
 * Skipped unless apcu + apc.enable_cli are available (CI / Docker bench environment).
 */
final class ParallelCollisionTest extends TestCase
{
    private const PROCESSES = 6;

    private const IDS_PER_PROCESS = 2_000;

    protected function setUp(): void
    {
        if (! extension_loaded('apcu') || ! ini_get('apc.enable_cli')) {
            self::markTestSkipped('APCu extension or apc.enable_cli not available');
        }
        if (! function_exists('proc_open')) {
            self::markTestSkipped('proc_open not available');
        }
    }

    public function test_parallel_processes_emit_zero_duplicate_ids(): void
    {
        // Given — N child PHP processes share APCu (single host) and a FileLock allocator
        $script = $this->writeChildScript();
        $lockDir = sys_get_temp_dir().'/konayuki-paralleltest-'.bin2hex(random_bytes(4));
        @mkdir($lockDir, 0775, true);

        try {
            // When — fork N children concurrently and drain stdout
            $procs = [];
            $pipes = [];
            for ($i = 0; $i < self::PROCESSES; $i++) {
                $proc = proc_open(
                    [PHP_BINARY, '-d', 'apc.enable_cli=1', $script, $lockDir, (string) self::IDS_PER_PROCESS],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $childPipes,
                );
                self::assertIsResource($proc, "proc_open failed for child #{$i}");
                $procs[$i] = $proc;
                $pipes[$i] = $childPipes[1];
                fclose($childPipes[2]);
                stream_set_blocking($childPipes[1], false);
            }

            // Drain all stdout pipes concurrently with stream_select to avoid SO_SNDBUF deadlock.
            $buffers = array_fill(0, self::PROCESSES, '');
            $open = $pipes;
            while ($open !== []) {
                $read = $open;
                $write = null;
                $except = null;
                if (stream_select($read, $write, $except, 10) === false) {
                    break;
                }
                foreach ($read as $p) {
                    $idx = (int) array_search($p, $pipes, true);
                    $chunk = fread($p, 65536);
                    if ($chunk === false || $chunk === '') {
                        if (feof($p)) {
                            fclose($p);
                            unset($open[(int) array_search($p, $open, true)]);
                        }

                        continue;
                    }
                    $buffers[$idx] .= $chunk;
                }
            }

            $exitCodes = [];
            foreach ($procs as $i => $proc) {
                $exitCodes[$i] = proc_close($proc);
            }

            // Then — every child exited cleanly and the union of IDs has zero duplicates.
            foreach ($exitCodes as $i => $code) {
                self::assertSame(0, $code, "Child #{$i} exited non-zero (stdout: {$buffers[$i]})");
            }

            $all = [];
            foreach ($buffers as $buf) {
                foreach (explode("\n", $buf) as $line) {
                    if ($line === '') {
                        continue;
                    }
                    $all[] = $line;
                }
            }
            $expected = self::PROCESSES * self::IDS_PER_PROCESS;
            self::assertCount($expected, $all, 'Total ID count matches PROCESSES × IDS_PER_PROCESS');
            self::assertCount($expected, array_unique($all), "Zero collisions across {$expected} IDs from ".self::PROCESSES.' parallel processes');
        } finally {
            @unlink($script);
            foreach (glob($lockDir.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($lockDir);
        }
    }

    private function writeChildScript(): string
    {
        $autoload = realpath(__DIR__.'/../../vendor/autoload.php');
        self::assertNotFalse($autoload, 'vendor/autoload.php must exist');

        $code = <<<PHP
            <?php

            declare(strict_types=1);

            require '{$autoload}';

            \$lockDir = \$argv[1] ?? '';
            \$count   = (int) (\$argv[2] ?? 0);

            \$allocator = new \\Konayuki\\FileLock\\FileLockWorkerIdAllocator(\$lockDir, maxWorkers: 1024);
            \$generator = new \\Konayuki\\IdGenerator(
                counter:   new \\Konayuki\\Apcu\\ApcuAtomicCounter,
                clock:     new \\Konayuki\\SystemClock,
                layout:    \\Konayuki\\Layout::default(),
                timestamp: new \\Konayuki\\RealTimestamp,
                sequence:  new \\Konayuki\\Sequence\\MonotonicSequenceStrategy,
                workerId:  \$allocator->acquire(),
            );

            \$buf = '';
            for (\$i = 0; \$i < \$count; \$i++) {
                \$buf .= \$generator->next()->value . "\\n";
            }
            echo \$buf;
            exit(0);
            PHP;

        $path = tempnam(sys_get_temp_dir(), 'konayuki-child-');
        self::assertNotFalse($path, 'tempnam failed');
        file_put_contents($path, $code);

        return $path;
    }
}
