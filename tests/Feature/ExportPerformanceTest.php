<?php

use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Company;
use Tests\Fixtures\ExportBenchmarkTable;
use Tests\Fixtures\Role;

test('export benchmark reports runtime queries memory row count and output size', function () {
    if (getenv('RUN_EXPORT_BENCHMARKS') !== '1') {
        $this->markTestSkipped('Set RUN_EXPORT_BENCHMARKS=1 to run the 1k/10k/100k export benchmark.');
    }

    $company = Company::create(['name' => 'Benchmark Company']);
    $role = Role::create(['name' => 'Benchmark Role']);
    $timestamp = now();
    $ids = [];

    for ($offset = 0; $offset < 100000; $offset += 500) {
        $users = [];
        for ($index = $offset; $index < min(100000, $offset + 500); $index++) {
            $users[] = [
                'name' => sprintf('Benchmark %06d', $index),
                'email' => "benchmark-{$index}@example.test",
                'is_admin' => false,
                'company_id' => $company->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }
        DB::table('users')->insert($users);
    }

    $ids = DB::table('users')->orderBy('id')->pluck('id')->all();
    for ($offset = 0; $offset < count($ids); $offset += 500) {
        $pivots = [];
        foreach (array_slice($ids, $offset, 500) as $id) {
            $pivots[] = ['user_id' => $id, 'role_id' => $role->id];
        }
        DB::table('role_user')->insert($pivots);
    }

    $baselineId = (int) $ids[0];
    $results = [];
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    foreach ([1000, 10000, 100000] as $size) {
        $maxId = (int) $ids[$size - 1];
        foreach (['flat', 'singular', 'collection', 'mixed'] as $shape) {
            $queries = 0;

            $bytes = 0;
            $lines = 0;
            $baseMemory = memory_get_usage();
            if (function_exists('memory_reset_peak_usage')) {
                memory_reset_peak_usage();
            }
            $started = hrtime(true);

            ob_start(function (string $output) use (&$bytes, &$lines): string {
                $bytes += strlen($output);
                $lines += substr_count($output, "\n");

                return '';
            }, 8192);
            $parameters = [
                'table_class' => encrypt(ExportBenchmarkTable::class),
                'export_name' => 'csv',
                'shape' => $shape,
                'min_id' => $baselineId,
                'max_id' => $maxId,
            ];
            if ($shape === 'mixed') {
                $parameters += [
                    'search' => 'BENCHMARK',
                    'filter' => ['is_admin' => '0'],
                    'sort' => 'company.name',
                    'dir' => 'asc',
                ];
            }
            $response = $this->get(route('inertia-table.export', $parameters));
            $response->sendContent();
            ob_end_flush();

            if ($response->getStatusCode() !== 200) {
                fwrite(STDERR, 'Benchmark response: '.$response->getStatusCode().' '.$response->headers->get('Location').' '.var_export(session()->all(), true).PHP_EOL);
            }

            $elapsedMs = (hrtime(true) - $started) / 1_000_000;
            $peakBytes = max(0, memory_get_peak_usage() - $baseMemory);
            $exportedRows = max(0, $lines - 1); // Header is the first CSV line.

            expect($response->getStatusCode())->toBe(200)
                ->and($exportedRows)->toBe($size)
                ->and($bytes)->toBeGreaterThan($size);

            $results[] = [
                'records' => $size,
                'shape' => $shape,
                'queries' => $queries,
                'runtime_ms' => round($elapsedMs, 1),
                'peak_memory_mb' => round($peakBytes / 1024 / 1024, 2),
                'rows' => $exportedRows,
                'csv_bytes' => $bytes,
            ];
        }
    }

    fwrite(STDOUT, 'EXPORT_BENCHMARK='.json_encode($results, JSON_UNESCAPED_SLASHES).PHP_EOL);
});
