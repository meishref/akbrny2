<?php

namespace Database\Seeders;

use App\Answer;
use App\Post;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo seed data based on schema metadata from localh202ost.sql (akbrny2_home2).
 *
 * Tables covered: users, posts (messages + polls), answers.
 * OAuth / password_resets / notifications left empty (runtime data).
 *
 * Demo login password for all seeded users: password
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->createUser([
            'name' => 'مدير النظام',
            'email' => 'admin@akbrny.test',
            'username' => 'admin',
            'text_profile' => 'حساب تجريبي للإدارة',
            'visitors' => 120,
            'is_public' => 1,
            'accept_posts' => 1,
            'show_zwar' => 1,
            'active_notification' => 1,
            'web' => 'https://akbrny.com',
            'twitter' => 'akbrny',
            'tiktok' => 'akbrny',
            'words_block' => "spam\nbadword",
        ]);

        $sara = $this->createUser([
            'name' => 'سارة أحمد',
            'email' => 'sara@akbrny.test',
            'username' => 'sara',
            'text_profile' => 'أسئلة مجهولة مرحب بها',
            'visitors' => 45,
            'instagram' => 'sara.demo',
            'telegram' => 'sara_demo',
            'tiktok' => null,
            'words_block' => null,
        ]);

        $omar = $this->createUser([
            'name' => 'عمر حسن',
            'email' => 'omar@akbrny.test',
            'username' => 'omar',
            'text_profile' => 'حساب تجريبي',
            'visitors' => 18,
            'facebook' => 'omar.demo',
            'android_token' => null,
            'user_pass' => null,
        ]);

        $guestLike = $this->createUser([
            'name' => 'مستخدم زائر',
            'email' => 'guest@akbrny.test',
            'username' => 'guestuser',
            'text_profile' => null,
            'visitors' => 3,
            'is_public' => 1,
            'accept_posts' => 1,
        ]);

        // Messages (type = 0) sent TO a user (user_id = recipient)
        $this->createMessage($sara->id, 'مرحبا سارة، ما رأيك في الفكرة؟', isPublic: false, isRead: false);
        $this->createMessage($sara->id, 'سؤال سري: ما أفضل كتاب قرأتيه؟', isPublic: false, isRead: true);
        $this->createMessage($sara->id, 'رسالة عامة ظاهرة على الملف', isPublic: true, isRead: true);
        $this->createMessage($omar->id, 'أهلا عمر، كيف الحال؟', isPublic: false, isRead: false);
        $this->createMessage($admin->id, 'ملاحظة تجريبية للوحة التحكم', isPublic: false, isRead: false);
        $this->createMessage($guestLike->id, 'رسالة لمستخدم جديد', isPublic: true, isRead: true);

        // Polls (type = 1)
        $poll1 = $this->createPoll(
            $sara->id,
            'ما لونك المفضل؟',
            ['أحمر', 'أزرق', 'أخضر', 'أصفر'],
            isPublic: true,
        );

        $poll2 = $this->createPoll(
            $omar->id,
            'أفضل وقت للعمل؟',
            ['صباحاً', 'مساءً', 'ليلاً', null],
            isPublic: true,
        );

        $poll3 = $this->createPoll(
            $admin->id,
            'هل تستخدم اخبرني يومياً؟',
            ['نعم', 'أحياناً', 'نادراً', 'لا'],
            isPublic: false,
        );

        // Votes / answers (body stores selected option text — matches app usage)
        $this->vote($poll1, $omar->id, 'أزرق');
        $this->vote($poll1, $admin->id, 'أخضر');
        $this->vote($poll1, $guestLike->id, 'أحمر');

        $this->vote($poll2, $sara->id, 'صباحاً');
        $this->vote($poll2, $admin->id, 'مساءً');

        $this->vote($poll3, $sara->id, 'أحياناً');
        $this->vote($poll3, $omar->id, 'نعم');

        $this->command?->info('Demo seed complete.');
        $this->command?->table(
            ['Username', 'Email', 'Password'],
            [
                ['admin', 'admin@akbrny.test', 'password'],
                ['sara', 'sara@akbrny.test', 'password'],
                ['omar', 'omar@akbrny.test', 'password'],
                ['guestuser', 'guest@akbrny.test', 'password'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(array $attributes): User
    {
        $user = new User();
        $user->forceFill(array_merge([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active' => 1,
            'is_public' => 1,
            'accept_posts' => 1,
            'show_zwar' => 1,
            'active_notification' => 1,
            'visitors' => 0,
            'image' => null,
            'token_notification' => null,
            'ip_address' => '127.0.0.1',
        ], $attributes));
        $user->save();

        return $user;
    }

    private function createMessage(int $recipientId, string $body, bool $isPublic, bool $isRead): Post
    {
        return Post::create([
            'user_id' => $recipientId,
            'type' => 0,
            'body' => $body,
            'is_public' => $isPublic ? 1 : 0,
            'is_read' => $isRead ? 1 : 0,
            'post_is_fav' => 0,
            'post_time' => null,
            'is_active' => 1,
            'ip' => '127.0.0.1',
        ]);
    }

    /**
     * @param  array{0: ?string, 1: ?string, 2: ?string, 3: ?string}  $options
     */
    private function createPoll(int $ownerId, string $question, array $options, bool $isPublic): Post
    {
        return Post::create([
            'user_id' => $ownerId,
            'type' => 1,
            'body' => $question,
            'answer1' => $options[0] ?? null,
            'answer2' => $options[1] ?? null,
            'answer3' => $options[2] ?? null,
            'answer4' => $options[3] ?? null,
            'is_public' => $isPublic ? 1 : 0,
            'is_read' => 1,
            'post_is_fav' => 0,
            'post_time' => now()->format('Y-m-d H:i:s'),
            'is_active' => 1,
            'ip' => '127.0.0.1',
        ]);
    }

    private function vote(Post $poll, int $voterId, string $selectedOption): void
    {
        Answer::create([
            'post_id' => $poll->id,
            'user_id' => $voterId,
            'body' => $selectedOption,
        ]);
    }
}
