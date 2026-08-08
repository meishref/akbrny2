<?php

use App\Console\PerformanceBaseline;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('performance:baseline', function () {
    $this->info('Running performance baseline (no optimizations applied)...');
    $results = (new PerformanceBaseline())->run();

    $rows = array_map(fn ($r) => [
        $r['endpoint'],
        $r['status'],
        $r['time_ms'].' ms',
        $r['query_count'],
        number_format($r['memory_bytes'] / 1024, 1).' KB',
        $r['error'] ?? '',
    ], $results);

    $this->table(['Endpoint', 'HTTP', 'Time', 'Queries', 'Memory Δ', 'Error'], $rows);

    $path = base_path('storage/app/performance-baseline.json');
    file_put_contents($path, json_encode([
        'captured_at' => now()->toIso8601String(),
        'environment' => [
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'session_driver' => config('session.driver'),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
        ],
        'results' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $this->info("Raw results written to: {$path}");
})->purpose('Capture performance baseline metrics for PERFORMANCE.md');
