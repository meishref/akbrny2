#!/usr/bin/env php
<?php

/**
 * Read-only verification of staging seed data integrity.
 *
 * Usage (on staging server):
 *   APP_ENV=staging php scripts/verify_staging_data.php
 *
 * Does NOT modify the database.
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const STAGING_USER_PREFIX = 'staging_test_2026_';
const STAGING_POST_IP = 'staging-seed';
const STAGING_TOKEN_PREFIX = 'STAGING_FAKE_FCM_';

if (app()->environment('production')) {
    fwrite(STDERR, "ABORT: refuse to run verification with APP_ENV=production\n");
    exit(1);
}

echo "=== Staging Data Verification (read-only) ===\n";
echo 'Environment: '.app()->environment()."\n";
echo 'Database: '.config('database.connections.'.config('database.default').'.database')."\n\n";

$stagingUserIds = DB::table('users')
    ->where('username', 'like', STAGING_USER_PREFIX.'%')
    ->pluck('id')
    ->map(fn ($id) => (int) $id)
    ->all();

$stagingPostIds = DB::table('posts')
    ->where('ip', STAGING_POST_IP)
    ->pluck('id')
    ->map(fn ($id) => (int) $id)
    ->all();

$counts = [
    'staging_users' => count($stagingUserIds),
    'staging_posts' => count($stagingPostIds),
    'staging_answers' => DB::table('answers')->whereIn('post_id', $stagingPostIds)->count(),
    'staging_notifications' => DB::table('notifications')
        ->where('token', 'like', STAGING_TOKEN_PREFIX.'%')
        ->count(),
    'all_users' => DB::table('users')->count(),
    'all_posts' => DB::table('posts')->count(),
    'all_answers' => DB::table('answers')->count(),
    'all_notifications' => DB::table('notifications')->count(),
];

foreach ($counts as $label => $value) {
    echo str_pad($label.':', 28).' '.$value."\n";
}

echo "\n--- Referential integrity (staging scope) ---\n";

$orphanPosts = DB::table('posts')
    ->where('ip', STAGING_POST_IP)
    ->whereNotIn('user_id', $stagingUserIds)
    ->count();

$orphanAnswersUser = DB::table('answers')
    ->whereIn('post_id', $stagingPostIds)
    ->whereNotIn('user_id', $stagingUserIds)
    ->count();

$orphanAnswersPost = DB::table('answers as a')
    ->leftJoin('posts as p', 'a.post_id', '=', 'p.id')
    ->whereIn('a.post_id', $stagingPostIds)
    ->whereNull('p.id')
    ->count();

$orphanNotifications = DB::table('notifications')
    ->where('token', 'like', STAGING_TOKEN_PREFIX.'%')
    ->whereNotIn('user_id', $stagingUserIds)
    ->count();

$checks = [
    'posts.user_id -> staging users' => $orphanPosts,
    'answers.user_id -> staging users' => $orphanAnswersUser,
    'answers.post_id -> staging posts' => $orphanAnswersPost,
    'notifications.user_id -> staging users' => $orphanNotifications,
];

$failed = false;

foreach ($checks as $label => $orphans) {
    $status = $orphans === 0 ? 'OK' : 'FAIL';
    echo str_pad($label.':', 40)." {$status} (orphans: {$orphans})\n";
    if ($orphans !== 0) {
        $failed = true;
    }
}

$dupEmails = DB::table('users')
    ->select('email')
    ->groupBy('email')
    ->havingRaw('COUNT(*) > 1')
    ->count();

$dupUsernames = DB::table('users')
    ->select('username')
    ->groupBy('username')
    ->havingRaw('COUNT(*) > 1')
    ->count();

echo "\n--- Uniqueness (full users table) ---\n";
echo 'duplicate emails: '.$dupEmails."\n";
echo 'duplicate usernames: '.$dupUsernames."\n";

if ($dupEmails > 0 || $dupUsernames > 0) {
    $failed = true;
}

echo "\nResult: ".($failed ? 'FAILED' : 'PASSED')."\n";
exit($failed ? 1 : 0);
