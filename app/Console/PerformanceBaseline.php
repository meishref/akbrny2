<?php

namespace App\Console;

use App\Answer;
use App\Post;
use App\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Internal baseline harness — not part of the API contract test suite.
 * Run: php8.4 artisan performance:baseline
 */
class PerformanceBaseline
{
    private array $results = [];

    public function run(): array
    {
        $this->seedBaselineData();

        $this->measure('GET / (home)', fn () => $this->get('/'));
        $this->measure('GET /login', fn () => $this->get('/login'));
        $this->measure('GET /register', fn () => $this->get('/register'));
        $this->measure('GET /password/reset', fn () => $this->get('/password/reset'));
        $this->measure('POST /login', fn () => $this->post('/login', [
            'email' => 'perf@example.com',
            'password' => 'password',
        ]));
        $this->measure('GET /user (dashboard)', fn () => $this->authenticatedGet('/user', 'perfuser'));
        $this->measure('GET /user/settings', fn () => $this->authenticatedGet('/user/settings', 'perfuser'));
        $this->measure('GET /user/notification', fn () => $this->authenticatedGet('/user/notification', 'perfuser'));
        $this->measure('GET /{username} (profile)', fn () => $this->get('/perfuser'));
        $this->measure('POST /{username} (send message)', fn () => $this->post('/any', [
            'message' => 'Benchmark message',
            'user_id' => User::where('username', 'perfuser')->value('id'),
        ]));
        $this->measure('GET /ajax/site/search', fn () => $this->get('/ajax/site/search?query=perf'));
        $this->measure('POST /ajax/email/check', fn () => $this->post('/ajax/email/check', [
            'email' => 'newbench@example.com',
        ]));
        $this->measure('POST /ajax/message/reply', fn () => $this->authenticatedPostJson('/ajax/message/reply', 'perfuser', [
            'reply' => 'Thanks',
            'message_reply_id' => Post::where('user_id', User::where('username', 'perfuser')->value('id'))->where('type', 0)->value('id'),
        ]));
        $this->measure('POST /ajax/profile/profileSendVote', fn () => $this->authenticatedPostJson('/ajax/profile/profileSendVote', 'voteruser', [
            'post_id' => Post::where('type', 1)->value('id'),
            'select_id' => 2,
        ]));
        $this->measure('POST /ajax/user/saveNotificationToken', fn () => $this->authenticatedPostJson('/ajax/user/saveNotificationToken', 'perfuser', [
            'currentToken' => 'bench-token-'.time(),
        ]));
        $this->measure('POST /ajax/question/add', fn () => $this->authenticatedPostJson('/ajax/question/add', 'perfuser', [
            'question' => 'Bench poll?',
            'option1' => 'Yes',
            'option2' => 'No',
        ]));
        $this->measure('POST /ajax/user/edit_info', fn () => $this->authenticatedPostJson('/ajax/user/edit_info', 'perfuser', [
            'name' => 'Perf User',
            'email' => 'perf@example.com',
            'text_profile' => 'Bio',
        ]));
        $this->measure('GET /api/user', fn () => $this->getJson('/api/user'));

        return $this->results;
    }

    private function seedBaselineData(): void
    {
        DB::table('answers')->whereIn('user_id', function ($q) {
            $q->select('id')->from('users')->whereIn('username', ['perfuser', 'voteruser']);
        })->delete();
        Post::whereIn('user_id', User::whereIn('username', ['perfuser', 'voteruser'])->pluck('id'))->delete();
        User::whereIn('username', ['perfuser', 'voteruser'])->delete();

        $owner = User::create([
            'name' => 'Perf User',
            'email' => 'perf@example.com',
            'username' => 'perfuser',
            'password' => Hash::make('password'),
            'active' => 1,
            'is_public' => 1,
            'accept_posts' => 1,
            'show_zwar' => 1,
            'active_notification' => 0,
            'visitors' => 0,
        ]);

        User::create([
            'name' => 'Voter User',
            'email' => 'voter@example.com',
            'username' => 'voteruser',
            'password' => Hash::make('password'),
            'active' => 1,
            'is_public' => 1,
            'accept_posts' => 1,
            'show_zwar' => 1,
            'active_notification' => 0,
            'visitors' => 0,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            $message = Post::create([
                'user_id' => $owner->id,
                'body' => "Benchmark message {$i}",
                'type' => 0,
                'is_public' => $i % 2,
                'is_read' => 0,
            ]);

            if ($i <= 5) {
                Answer::unguard();
                Answer::create([
                    'post_id' => $message->id,
                    'user_id' => $owner->id,
                    'body' => 'Reply body',
                ]);
                Answer::reguard();
            }
        }

        Post::create([
            'user_id' => $owner->id,
            'body' => 'Benchmark poll',
            'type' => 1,
            'answer1' => 'A',
            'answer2' => 'B',
            'answer3' => null,
            'answer4' => null,
            'is_active' => 1,
            'is_public' => 1,
        ]);
    }

    private function measure(string $label, callable $callback): void
    {
        app()->forgetInstance('view.num_message_unread');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $memoryBefore = memory_get_usage(true);
        $start = hrtime(true);

        try {
            $response = $callback();
            $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;
            $error = null;
        } catch (\Throwable $e) {
            $status = 500;
            $error = $e->getMessage();
        }

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $memoryPeak = memory_get_peak_usage(true) - $memoryBefore;
        $queries = DB::getQueryLog();

        $this->results[] = [
            'endpoint' => $label,
            'status' => $status,
            'time_ms' => round($elapsedMs, 2),
            'query_count' => count($queries),
            'memory_bytes' => $memoryPeak,
            'error' => $error,
            'queries' => array_map(fn ($q) => [
                'sql' => $q['query'],
                'time_ms' => $q['time'] ?? null,
            ], $queries),
        ];

        Auth::logout();
    }

    private function get(string $uri)
    {
        return $this->dispatch('GET', $uri);
    }

    private function getJson(string $uri)
    {
        return $this->dispatch('GET', $uri, [], ['HTTP_ACCEPT' => 'application/json']);
    }

    private function post(string $uri, array $data = [])
    {
        return $this->dispatch('POST', $uri, $data);
    }

    private function authenticatedGet(string $uri, string $username)
    {
        $user = User::where('username', $username)->first();
        Auth::login($user);

        return $this->dispatch('GET', $uri);
    }

    private function authenticatedPostJson(string $uri, string $username, array $data)
    {
        $user = User::where('username', $username)->first();
        Auth::login($user);

        return $this->dispatch('POST', $uri, $data, [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);
    }

    private function dispatch(string $method, string $uri, array $data = [], array $server = [])
    {
        $kernel = app(Kernel::class);
        $request = Request::create($uri, $method, $data, [], [], $server);
        $request->headers->set('Accept', $server['HTTP_ACCEPT'] ?? 'text/html');

        if ($method !== 'GET') {
            $token = csrf_token();
            $data['_token'] = $token;
            $request = Request::create($uri, $method, $data, [], [], $server);
            $request->headers->set('X-CSRF-TOKEN', $token);
            if (isset($server['HTTP_ACCEPT'])) {
                $request->headers->set('Accept', $server['HTTP_ACCEPT']);
            }
            if (isset($server['HTTP_X-Requested-With'])) {
                $request->headers->set('X-Requested-With', $server['HTTP_X-Requested-With']);
            }
        }

        if (Auth::check()) {
            $request->setUserResolver(fn () => Auth::user());
        }

        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
