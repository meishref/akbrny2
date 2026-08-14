#!/usr/bin/env php
<?php

/**
 * Read-only performance diagnostics for staging data.
 *
 * Reports measured query counts and wall-clock time only — no invented percentages.
 *
 * Usage (on staging server after seed):
 *   APP_ENV=staging php scripts/staging_performance_check.php
 */

declare(strict_types=1);

use App\Answer;
use App\Post;
use App\User;
use App\Services\SiteSearchService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (app()->environment('production')) {
    fwrite(STDERR, "ABORT: refuse to run with APP_ENV=production\n");
    exit(1);
}

const STAGING_USER_PREFIX = 'staging_test_2026_';
const STAGING_POST_IP = 'staging-seed';

echo "=== Staging Performance Check (read-only) ===\n";
echo 'Environment: '.app()->environment()."\n";
echo 'Database: '.config('database.connections.'.config('database.default').'.database')."\n\n";

/**
 * @return array{label: string, ms: float, queries: int}
 */
function measure(string $label, callable $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $start = microtime(true);
    $callback();
    $ms = round((microtime(true) - $start) * 1000, 2);
    $queries = count(DB::getQueryLog());

    DB::disableQueryLog();

    return compact('label', 'ms', 'queries');
}

$sampleUser = User::query()
    ->where('username', 'like', STAGING_USER_PREFIX.'%')
    ->where('active', 1)
    ->orderBy('id')
    ->first();

if ($sampleUser === null) {
    fwrite(STDERR, "No staging users found. Run StagingDataSeeder first.\n");
    exit(1);
}

$results = [];

$results[] = measure('Homepage / recent public posts', function () {
    Post::query()
        ->where('is_public', 1)
        ->orderByDesc('id')
        ->limit(20)
        ->get(['id', 'body', 'user_id', 'type', 'created_at']);
});

$results[] = measure('Profile posts + eager answers (N+1-sensitive)', function () use ($sampleUser) {
    $allPosts = Post::query()
        ->where('user_id', $sampleUser->id)
        ->with('answers')
        ->get();

    $pollIds = $allPosts->where('type', 1)->pluck('id');

    if ($pollIds->isNotEmpty()) {
        Answer::query()
            ->where('user_id', $sampleUser->id)
            ->whereIn('post_id', $pollIds)
            ->get()
            ->keyBy('post_id');
    }
});

$results[] = measure('Search (common term "STAGING")', function () {
    app(SiteSearchService::class)->searchHtml('STAGING');
});

$results[] = measure('Search (partial username)', function () use ($sampleUser) {
    app(SiteSearchService::class)->searchHtml(substr($sampleUser->username, 0, 20));
});

$results[] = measure('Search (empty query)', function () {
    app(SiteSearchService::class)->searchHtml('');
});

$results[] = measure('Poll voting lookup (exists checks)', function () use ($sampleUser) {
    $poll = Post::query()
        ->where('ip', STAGING_POST_IP)
        ->where('type', 1)
        ->inRandomOrder()
        ->first();

    if ($poll) {
        Post::query()->where('id', $poll->id)->where('type', 1)->exists();
        Answer::query()
            ->where('user_id', $sampleUser->id)
            ->where('post_id', $poll->id)
            ->exists();
    }
});

$results[] = measure('Answers aggregate on staging poll', function () {
    $pollId = Post::query()
        ->where('ip', STAGING_POST_IP)
        ->where('type', 1)
        ->orderByDesc('id')
        ->value('id');

    if ($pollId) {
        Answer::query()->where('post_id', $pollId)->count();
    }
});

$results[] = measure('Notifications for staging user', function () use ($sampleUser) {
    DB::table('notifications')
        ->where('user_id', $sampleUser->id)
        ->limit(50)
        ->get();
});

echo str_pad('Scenario', 42).' '.str_pad('Queries', 10).' '.str_pad('Time (ms)', 12)."\n";
echo str_repeat('-', 66)."\n";

foreach ($results as $row) {
    echo str_pad($row['label'], 42).' '
        .str_pad((string) $row['queries'], 10).' '
        .str_pad((string) $row['ms'], 12)."\n";
}

echo "\nSample profile user: {$sampleUser->username} (id {$sampleUser->id})\n";
echo "Note: Query counts for profile should stay low regardless of poll count (batch load).\n";
echo "Done.\n";
