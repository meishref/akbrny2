<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Large-scale staging test data for the EXISTING client schema.
 *
 * Inserts ONLY new rows prefixed with staging_test_2026_ — never updates existing records.
 *
 * Required to run:
 *   APP_ENV=staging
 *   ALLOW_STAGING_SEED=true
 *   Database must not match production blocklist (see assertSafeEnvironment).
 */
class StagingDataSeeder extends Seeder
{
    private const MARKER = 'staging_test_2026_';

    private const EMAIL_DOMAIN = 'example.test';

    private const SEED_IP = 'staging-seed';

    private const USER_COUNT = 10_000;

    private const POST_COUNT = 30_000;

    private const ANSWER_COUNT = 80_000;

    private const NOTIFICATION_COUNT = 12_000;

    private const CHUNK_SIZE = 500;

    /** @var list<string> */
    private array $metrics = [];

    public function run(): void
    {
        $this->assertSafeEnvironment();

        if ($this->stagingDataAlreadyPresent()) {
            throw new RuntimeException(
                'Staging seed data already exists (found '.self::MARKER.'000001). '.
                'Aborting to avoid duplicate inserts. Remove staging rows manually if re-seed is required.'
            );
        }

        $started = microtime(true);
        $passwordHash = Hash::make('staging-test-2026-not-for-production');

        $this->command?->info('Starting staging data seed (insert-only, prefixed rows)...');

        DB::connection()->transaction(function () use ($passwordHash) {
            $this->seedUsers($passwordHash);
            $userIds = $this->fetchStagingUserIds();

            if (count($userIds) !== self::USER_COUNT) {
                throw new RuntimeException(
                    'Expected '.self::USER_COUNT.' staging users after insert, got '.count($userIds)
                );
            }

            $pollPostIds = $this->seedPosts($userIds);
            $this->seedAnswers($userIds, $pollPostIds);
            $this->seedNotifications($userIds);
        });

        $duration = round(microtime(true) - $started, 2);
        $this->recordMetrics($duration);

        $this->command?->info("Staging seed finished in {$duration}s.");
        $this->command?->table(['Metric', 'Count'], array_map(
            fn ($k, $v) => [$k, $v],
            array_keys($this->metrics),
            array_values($this->metrics),
        ));
    }

    private function assertSafeEnvironment(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('ABORT: APP_ENV=production — staging seeder must never run in production.');
        }

        if (! app()->environment('staging')) {
            throw new RuntimeException(
                'ABORT: APP_ENV must be staging (current: '.app()->environment().').'
            );
        }

        if (! filter_var(env('ALLOW_STAGING_SEED', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException(
                'ABORT: Set ALLOW_STAGING_SEED=true in the environment before running this seeder.'
            );
        }

        $database = (string) config('database.connections.'.config('database.default').'.database');

        if ($database === '') {
            throw new RuntimeException('ABORT: Database name is empty.');
        }

        if ($this->isBlockedProductionDatabase($database)) {
            throw new RuntimeException(
                "ABORT: Database [{$database}] matches the production blocklist."
            );
        }
    }

    private function isBlockedProductionDatabase(string $database): bool
    {
        $blocked = array_map('trim', explode(',', (string) env(
            'STAGING_SEED_BLOCKED_DATABASES',
            'akbrny2_home2,production,prod,akbrny_production,akbrny_prod'
        )));

        foreach ($blocked as $name) {
            if ($name !== '' && strcasecmp($database, $name) === 0) {
                return true;
            }
        }

        $lower = strtolower($database);

        if (preg_match('/(^|_)prod(uction)?($|_)/', $lower) && ! str_contains($lower, 'staging') && ! str_contains($lower, 'test')) {
            return true;
        }

        return false;
    }

    private function stagingDataAlreadyPresent(): bool
    {
        return DB::table('users')
            ->where('username', self::MARKER.'000001')
            ->exists();
    }

    /**
     * @return list<int>
     */
    private function fetchStagingUserIds(): array
    {
        return DB::table('users')
            ->where('username', 'like', self::MARKER.'%')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function seedUsers(string $passwordHash): void
    {
        $rows = [];
        $baseTime = strtotime('2024-01-01 00:00:00');

        for ($i = 1; $i <= self::USER_COUNT; $i++) {
            $suffix = str_pad((string) $i, 6, '0', STR_PAD_LEFT);
            $created = date('Y-m-d H:i:s', $baseTime + ($i * 97) % (86400 * 400));
            $active = ($i % 47 === 0) ? 2 : 1;
            $isPublic = ($i % 11 === 0) ? 0 : 1;

            $rows[] = [
                'name' => 'STAGING TEST USER '.$suffix,
                'email' => self::MARKER.$suffix.'@'.self::EMAIL_DOMAIN,
                'email_verified_at' => $created,
                'password' => $passwordHash,
                'user_pass' => null,
                'username' => self::MARKER.$suffix,
                'image' => null,
                'text_profile' => 'Staging profile '.$suffix,
                'ip_address' => '10.0.'.(($i >> 8) & 0xFF).'.'.($i & 0xFF),
                'visitors' => $i % 500,
                'active' => $active,
                'is_public' => $isPublic,
                'accept_posts' => ($i % 13 === 0) ? 0 : 1,
                'show_zwar' => ($i % 17 === 0) ? 0 : 1,
                'active_notification' => ($i % 19 === 0) ? 0 : 1,
                'token_notification' => ($i % 3 === 0)
                    ? 'STAGING_FAKE_FCM_USER_'.$suffix
                    : null,
                'android_token' => null,
                'web' => ($i % 5 === 0) ? 'https://example.test/u/'.$suffix : null,
                'twitter' => ($i % 7 === 0) ? 'stg_'.$suffix : null,
                'instagram' => null,
                'youtube' => null,
                'snapchat' => null,
                'telegram' => null,
                'facebook' => null,
                'linkedin' => null,
                'tiktok' => null,
                'words_block' => null,
                'remember_token' => null,
                'created_at' => $created,
                'updated_at' => $created,
            ];

            if (count($rows) >= self::CHUNK_SIZE) {
                DB::table('users')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('users')->insert($rows);
        }

        $this->metrics['users_inserted'] = self::USER_COUNT;
        $this->command?->info('Inserted '.self::USER_COUNT.' staging users.');
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int> poll post IDs
     */
    private function seedPosts(array $userIds): array
    {
        $pollCount = (int) round(self::POST_COUNT * 0.30);
        $messageCount = self::POST_COUNT - $pollCount;
        $pollPostIds = [];
        $rows = [];
        $baseTime = strtotime('2024-02-01 00:00:00');
        $userCount = count($userIds);

        for ($i = 1; $i <= self::POST_COUNT; $i++) {
            $isPoll = $i > $messageCount;
            $type = $isPoll ? 1 : 0;
            $ownerIdx = $this->weightedUserIndex($i, $userCount);
            $userId = $userIds[$ownerIdx];
            $created = date('Y-m-d H:i:s', $baseTime + ($i * 131) % (86400 * 300));
            $suffix = str_pad((string) $i, 6, '0', STR_PAD_LEFT);

            $row = [
                'type' => $type,
                'body' => ($isPoll ? 'STAGING TEST POLL ' : 'STAGING TEST MESSAGE ').$suffix,
                'answer1' => null,
                'answer2' => null,
                'answer3' => null,
                'answer4' => null,
                'is_public' => ($i % 4 === 0) ? 1 : 0,
                'is_read' => ($i % 3 === 0) ? 1 : 0,
                'post_is_fav' => $i % 20,
                'post_time' => $isPoll ? $created : null,
                'ip' => self::SEED_IP,
                'is_active' => 1,
                'user_id' => $userId,
                'created_at' => $created,
                'updated_at' => $created,
            ];

            if ($isPoll) {
                $row['answer1'] = 'Option A '.$suffix;
                $row['answer2'] = 'Option B '.$suffix;
                $row['answer3'] = 'Option C '.$suffix;
                $row['answer4'] = 'Option D '.$suffix;
            }

            $rows[] = $row;

            if (count($rows) >= self::CHUNK_SIZE) {
                DB::table('posts')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('posts')->insert($rows);
        }

        $pollPostIds = DB::table('posts')
            ->where('ip', self::SEED_IP)
            ->where('type', 1)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->metrics['posts_inserted'] = self::POST_COUNT;
        $this->metrics['poll_posts'] = count($pollPostIds);
        $this->command?->info('Inserted '.self::POST_COUNT.' posts ('.count($pollPostIds).' polls).');

        return $pollPostIds;
    }

    /**
     * @param  list<int>  $userIds
     * @param  list<int>  $pollPostIds
     */
    private function seedAnswers(array $userIds, array $pollPostIds): void
    {
        if ($pollPostIds === []) {
            throw new RuntimeException('No poll posts available for answer seeding.');
        }

        $pollCount = count($pollPostIds);
        $answersPerPoll = $this->buildAnswerDistribution($pollCount, self::ANSWER_COUNT);
        $rows = [];
        $inserted = 0;
        $baseTime = strtotime('2024-03-01 00:00:00');
        $userCount = count($userIds);
        $voteOptions = ['1', '2', '3', '4'];

        foreach ($pollPostIds as $pollIndex => $postId) {
            $target = $answersPerPoll[$pollIndex];

            for ($v = 0; $v < $target; $v++) {
                $voterIdx = ($pollIndex * 17 + $v * 13) % $userCount;
                $created = date('Y-m-d H:i:s', $baseTime + ($inserted * 53) % (86400 * 200));

                $rows[] = [
                    'body' => $voteOptions[($pollIndex + $v) % 4],
                    'user_id' => $userIds[$voterIdx],
                    'post_id' => $postId,
                    'created_at' => $created,
                    'updated_at' => $created,
                ];
                $inserted++;

                if (count($rows) >= self::CHUNK_SIZE) {
                    DB::table('answers')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('answers')->insert($rows);
        }

        if ($inserted !== self::ANSWER_COUNT) {
            throw new RuntimeException("Answer count mismatch: expected ".self::ANSWER_COUNT.", inserted {$inserted}");
        }

        $this->metrics['answers_inserted'] = $inserted;
        $this->command?->info("Inserted {$inserted} answers.");
    }

    /**
     * Realistic distribution: some polls 0, few 1-5, many 10-20, heavy tail 50+.
     *
     * @return list<int> answer count per poll (same order as $pollPostIds)
     */
    private function buildAnswerDistribution(int $pollCount, int $totalAnswers): array
    {
        $distribution = array_fill(0, $pollCount, 0);
        $zeroPolls = (int) round($pollCount * 0.18);
        $remaining = $totalAnswers;

        for ($i = $zeroPolls; $i < $pollCount; $i++) {
            $tier = ($i - $zeroPolls) % 100;
            if ($tier < 45) {
                $distribution[$i] = 1 + ($i % 5);
            } elseif ($tier < 80) {
                $distribution[$i] = 10 + ($i % 11);
            } else {
                $distribution[$i] = 50 + ($i % 40);
            }
        }

        $sum = array_sum($distribution);

        if ($sum > $totalAnswers) {
            $factor = $totalAnswers / max(1, $sum);
            foreach ($distribution as $idx => $count) {
                $distribution[$idx] = (int) floor($count * $factor);
            }
        }

        $sum = array_sum($distribution);
        $delta = $totalAnswers - $sum;
        $idx = $pollCount - 1;

        while ($delta > 0 && $idx >= $zeroPolls) {
            $distribution[$idx]++;
            $delta--;
            $idx--;
        }

        return $distribution;
    }

    /**
     * @param  list<int>  $userIds
     */
    private function seedNotifications(array $userIds): void
    {
        $rows = [];
        $inserted = 0;
        $baseTime = strtotime('2024-04-01 00:00:00');
        $devices = ['web', 'android', 'ios', 'pwa'];
        $userCount = count($userIds);

        for ($n = 1; $n <= self::NOTIFICATION_COUNT; $n++) {
            $userIdx = ($n * 7) % $userCount;
            $userId = $userIds[$userIdx];
            $suffix = str_pad((string) $n, 6, '0', STR_PAD_LEFT);
            $created = date('Y-m-d H:i:s', $baseTime + ($n * 61) % (86400 * 180));

            $rows[] = [
                'user_id' => $userId,
                'token' => 'STAGING_FAKE_FCM_'.$suffix.'_'.self::MARKER.$userId,
                'device' => $devices[$n % count($devices)],
                'created_at' => $created,
                'updated_at' => $created,
            ];
            $inserted++;

            if (count($rows) >= self::CHUNK_SIZE) {
                DB::table('notifications')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('notifications')->insert($rows);
        }

        $this->metrics['notifications_inserted'] = $inserted;
        $this->command?->info("Inserted {$inserted} notification token rows.");
    }

    private function weightedUserIndex(int $seed, int $userCount): int
    {
        $bucket = $seed % 1000;
        if ($bucket < 200) {
            return $seed % min(50, $userCount);
        }
        if ($bucket < 600) {
            return $seed % min(500, $userCount);
        }

        return $seed % $userCount;
    }

    private function recordMetrics(float $duration): void
    {
        $this->metrics['duration_seconds'] = $duration;
        $this->metrics['staging_users_total'] = DB::table('users')
            ->where('username', 'like', self::MARKER.'%')
            ->count();
        $this->metrics['staging_posts_total'] = DB::table('posts')
            ->where('ip', self::SEED_IP)
            ->count();
        $this->metrics['staging_answers_on_polls'] = DB::table('answers')
            ->whereIn('post_id', function ($q) {
                $q->select('id')->from('posts')->where('ip', self::SEED_IP)->where('type', 1);
            })
            ->count();
        $this->metrics['staging_notifications_total'] = DB::table('notifications')
            ->where('token', 'like', 'STAGING_FAKE_FCM_%')
            ->count();
    }
}
