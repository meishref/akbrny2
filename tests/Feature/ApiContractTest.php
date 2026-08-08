<?php

namespace Tests\Feature;

use App\Answer;
use App\Post;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * One test method per route documented in API_INVENTORY.md (33 total).
 *
 * @see API_INVENTORY.md
 */
class ApiContractTest extends TestCase
{
    protected function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'username' => 'testuser',
            'password' => Hash::make('password'),
            'active' => 1,
            'is_public' => 1,
            'accept_posts' => 1,
            'show_zwar' => 1,
            'active_notification' => 1,
            'visitors' => 0,
        ], $overrides));
    }

    protected function createAnswer(User $user, Post $post, string $body = 'A reply'): Answer
    {
        $answer = new Answer();
        $answer->post_id = $post->id;
        $answer->user_id = $user->id;
        $answer->body = $body;
        $answer->save();

        return $answer;
    }

    // --- A. Authentication (9) ---

    public function test_a1_get_login_form(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_a2_post_login_accepts_email_or_username(): void
    {
        $this->createUser();

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ])->assertRedirect('/user');
        $this->assertAuthenticated();
        $this->post('/logout');

        $this->post('/login', [
            'email' => 'testuser',
            'password' => 'password',
        ])->assertRedirect('/user');
        $this->assertAuthenticated();
    }

    public function test_a3_post_logout(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_a4_get_register_form(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_a5_post_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'username' => 'newuser',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
        ]);

        $response->assertRedirect('/user');
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'username' => 'newuser',
        ]);
    }

    public function test_a6_get_password_reset_request_form(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_a7_post_password_email(): void
    {
        $user = $this->createUser();

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertRedirect();
    }

    public function test_a8_get_password_reset_form_with_token(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]))->assertOk();
    }

    public function test_a9_post_password_reset(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpass12',
            'password_confirmation' => 'newpass12',
        ]);

        $response->assertRedirect('/home');
    }

    // --- B. Public pages and profile (6) ---

    public function test_b1_get_home(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_b2_get_profile_by_username(): void
    {
        $this->get('/unknownuser999')
            ->assertOk()
            ->assertSee('user not found');

        $this->createUser(['username' => 'alice']);

        $this->get('/alice')
            ->assertOk()
            ->assertSee('alice');
    }

    public function test_b3_post_send_message_to_user(): void
    {
        $recipient = $this->createUser(['username' => 'bob']);

        $response = $this->post('/ignored-url-segment', [
            'message' => 'Hello there',
            'user_id' => $recipient->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('msg', 'تم إرسال الرسالة بنجاح , شكرا لك .');
        $this->assertDatabaseHas('posts', [
            'user_id' => $recipient->id,
            'body' => 'Hello there',
            'type' => 0,
            'is_public' => 0,
        ]);
    }

    public function test_b4_get_pages_contact(): void
    {
        $this->get(route('pages.contact'))->assertOk();
    }

    public function test_b5_get_pages_privacy_policy(): void
    {
        $this->get(route('pages.privacy-policy'))->assertOk();
    }

    public function test_b6_get_pages_terms(): void
    {
        $this->get(route('pages.terms'))->assertOk();
    }

    // --- C. Authenticated dashboard (3) ---

    public function test_c1_get_user_dashboard(): void
    {
        $this->get('/user')->assertRedirect('/login');

        $user = $this->createUser();
        $this->actingAs($user);

        $this->get('/user')->assertOk();
    }

    public function test_c2_get_user_settings(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->get(route('user.settings'))->assertOk();
    }

    public function test_c3_get_user_notification(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->get(route('user.notification'))->assertOk();
    }

    // --- D. AJAX / JSON (14) ---

    public function test_d1_post_ajax_email_check(): void
    {
        $this->createUser(['email' => 'taken@example.com', 'username' => 'takenuser']);

        $this->post(route('email_available.check'), ['email' => 'new@example.com'])
            ->assertOk()
            ->assertContent('unique');

        $this->post(route('email_available.check'), ['email' => 'taken@example.com'])
            ->assertOk()
            ->assertContent('not_unique');

        $this->post(route('email_available.check'), [
            'email' => 'new2@example.com',
            'username' => 'takenuser',
        ])
            ->assertOk()
            ->assertContent('uniquenot_unique');
    }

    public function test_d2_post_ajax_message_reply(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->postJson(route('ajax.reply_message'), [])
            ->assertOk()
            ->assertJsonStructure(['errors']);

        $post = Post::create([
            'user_id' => $user->id,
            'body' => 'Hello',
            'type' => 0,
            'is_public' => 0,
        ]);

        $this->postJson(route('ajax.reply_message'), [
            'reply' => 'Thanks',
            'message_reply_id' => $post->id,
        ])
            ->assertOk()
            ->assertJson(['success' => trans('main.message_done_reply')]);
    }

    public function test_d3_post_ajax_message_delete_reply(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $post = Post::create([
            'user_id' => $user->id,
            'body' => 'Hello',
            'type' => 0,
            'is_public' => 0,
        ]);
        $answer = $this->createAnswer($user, $post);

        $this->postJson(route('ajax.delete_reply_message'), [
            'reply_id' => $answer->id,
        ])
            ->assertOk()
            ->assertJson(['success' => trans('main.message_done_reply')]);

        $this->assertDatabaseMissing('answers', ['id' => $answer->id]);
    }

    public function test_d4_post_ajax_message_edit(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $post = Post::create([
            'user_id' => $user->id,
            'body' => 'Hello',
            'type' => 0,
            'is_public' => 1,
        ]);

        $this->postJson(route('ajax.edit_message'), [
            'post_id' => $post->id,
            'type' => 1,
            'is_question' => 0,
        ])
            ->assertOk()
            ->assertJson(['success' => trans('main.message_done_hide')]);

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'is_public' => 0]);
    }

    public function test_d5_post_ajax_message_delete(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $post = Post::create([
            'user_id' => $user->id,
            'body' => 'Delete me',
            'type' => 0,
            'is_public' => 0,
        ]);

        $this->postJson(route('ajax.delete_message'), [
            'post_id' => $post->id,
        ])
            ->assertOk()
            ->assertJson(['success' => trans('main.message_deleted_done')]);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_d6_post_ajax_question_add(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->postJson(route('ajax.question_add'), [])
            ->assertOk()
            ->assertJsonStructure(['errors']);

        $this->postJson(route('ajax.question_add'), [
            'question' => 'Favorite color?',
            'option1' => 'Red',
            'option2' => 'Blue',
        ])
            ->assertOk()
            ->assertJson(['success' => trans('main.question_done_add')]);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'body' => 'Favorite color?',
            'type' => 1,
            'is_active' => 1,
            'is_public' => 1,
        ]);
    }

    public function test_d7_post_ajax_user_edit_info(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->postJson(route('ajax.userEditInfo'), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'text_profile' => 'Bio',
        ])
            ->assertOk()
            ->assertJson(['success' => trans('main.user_done_edit_info')]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'text_profile' => 'Bio',
        ]);
    }

    public function test_d8_post_ajax_user_change_password(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->postJson(route('ajax.userChangePassword'), [
            'current-password' => 'wrong',
            'password' => 'newpass12',
            'password_confirmation' => 'newpass12',
        ])
            ->assertOk()
            ->assertJson(['errors' => 'كلمة المرور الحالية غير صحيحة .']);

        $this->postJson(route('ajax.userChangePassword'), [
            'current-password' => 'password',
            'password' => 'newpass12',
            'password_confirmation' => 'newpass12',
        ])
            ->assertOk()
            ->assertJson(['success' => 'تم تغيير كلمة المرور بنجاح']);
    }

    public function test_d9_post_ajax_user_change_image(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $profileDir = public_path('images/profile');
        if (! is_dir($profileDir)) {
            mkdir($profileDir, 0755, true);
        }

        $png = base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAD0lEQVR42mP8/8AAAQMBAQEBAQEBAQ=='));
        $dataUri = 'data:image/png;base64,'.$png;

        $this->postJson(route('ajax.userChangeImage'), ['image' => $dataUri])
            ->assertOk()
            ->assertJson(['success' => ' تم تحديث الصورة بنجاح']);

        $user->refresh();
        $this->assertNotNull($user->image);
        $this->assertFileExists(public_path('images/profile/'.$user->image));
    }

    public function test_d10_post_ajax_user_edit_settings(): void
    {
        $user = $this->createUser([
            'is_public' => 1,
            'accept_posts' => 1,
            'show_zwar' => 1,
            'active_notification' => 1,
        ]);
        $this->actingAs($user);

        $this->postJson(route('ajax.userEditSettings'), [
            'is_public' => '1',
            'accept_posts' => '1',
        ])
            ->assertOk()
            ->assertJson(['success' => 'تم حفظ الاعدادات بنجاح']);

        $user->refresh();
        $this->assertTrue((bool) $user->is_public);
        $this->assertTrue((bool) $user->accept_posts);
        $this->assertFalse((bool) $user->show_zwar);
        $this->assertFalse((bool) $user->active_notification);
    }

    public function test_d11_post_ajax_user_edit_social(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->postJson(route('ajax.userEditSocial'), [
            'web' => 'https://example.com',
            'twitter' => 'https://twitter.com/user',
        ])
            ->assertOk()
            ->assertJson(['success' => 'تم حفظ الاعدادات بنجاح']);

        $user->refresh();
        $this->assertSame('https://example.com', $user->web);
        $this->assertSame('https://twitter.com/user', $user->twitter);
    }

    public function test_d12_post_ajax_profile_send_vote(): void
    {
        $owner = $this->createUser(['username' => 'owner']);
        $poll = Post::create([
            'user_id' => $owner->id,
            'body' => 'Question?',
            'type' => 1,
            'answer1' => 'A',
            'answer2' => 'B',
            'is_active' => 1,
            'is_public' => 1,
        ]);

        $this->postJson(route('ajax.profileSendVote'), [
            'post_id' => $poll->id,
            'select_id' => 1,
        ])
            ->assertOk()
            ->assertJson(['error' => 'يجب عليك التسجيل اولا للتصويت  ... ']);

        $voter = $this->createUser(['email' => 'voter@example.com', 'username' => 'voter']);
        $this->actingAs($voter);

        $this->postJson(route('ajax.profileSendVote'), [
            'post_id' => $poll->id,
            'select_id' => 1,
        ])
            ->assertOk()
            ->assertJson(['success' => 'تم التصويت بنجاح']);

        $this->assertDatabaseHas('answers', [
            'user_id' => $voter->id,
            'post_id' => $poll->id,
            'body' => '1',
        ]);
    }

    public function test_d13_post_ajax_save_notification_token(): void
    {
        $this->postJson(route('ajax.saveNotificationToken'), [
            'currentToken' => 'token123',
        ])
            ->assertOk()
            ->assertJson(['errors' => 'not login']);

        $user = $this->createUser();
        $this->actingAs($user);

        $this->postJson(route('ajax.saveNotificationToken'), [
            'currentToken' => 'token123',
        ])
            ->assertOk()
            ->assertJson(['success' => 'done save token']);

        $user->refresh();
        $this->assertSame('token123', $user->token_notification);

        $this->postJson(route('ajax.saveNotificationToken'), [
            'currentToken' => 'token123',
        ])
            ->assertOk()
            ->assertJson(['success' => 'token already saved']);
    }

    public function test_d14_get_ajax_site_search(): void
    {
        $this->getJson(route('ajax.siteSearch', ['query' => '']))
            ->assertOk()
            ->assertJson(['success' => 'لاتوجد نتائج لبحثك']);

        $this->createUser(['name' => 'Searchable', 'username' => 'searchable']);

        $response = $this->getJson(route('ajax.siteSearch', ['query' => 'Search']));
        $response->assertOk();
        $response->assertJsonStructure(['success']);
        $this->assertStringContainsString('list-group', $response->json('success'));
    }

    // --- E. routes/api.php (1) ---

    public function test_e1_get_api_user_stub(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }
}
