<?php

namespace Tests\Feature;

use App\Post;
use App\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    protected function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'API User',
            'email' => 'api@example.com',
            'username' => 'apiuser',
            'password' => Hash::make('password'),
            'active' => 1,
            'is_public' => 1,
        ], $overrides));
    }

    public function test_v1_search_returns_json_structure(): void
    {
        $this->createUser(['name' => 'Searchable API', 'username' => 'searchapi']);

        $this->getJson('/api/v1/search?query=Search')
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonPath('data.0.username', 'searchapi');
    }

    public function test_v1_search_empty_query_returns_empty_data(): void
    {
        $this->getJson('/api/v1/search?query=')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('message', 'لاتوجد نتائج لبحثك');
    }

    public function test_v1_messages_reply_requires_auth(): void
    {
        $this->postJson('/api/v1/messages/reply', [
            'message_reply_id' => 1,
            'reply' => 'test',
        ])->assertUnauthorized();
    }

    public function test_v1_messages_reply_success(): void
    {
        $user = $this->createUser();
        $post = Post::create([
            'user_id' => $user->id,
            'body' => 'Hello',
            'type' => 0,
            'is_public' => 0,
            'is_read' => 0,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/messages/reply', [
                'message_reply_id' => $post->id,
                'reply' => 'Thanks',
            ])
            ->assertOk()
            ->assertJsonPath('success', trans('main.message_done_reply'));
    }
}
