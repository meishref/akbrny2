<?php

namespace Tests\Feature;

use App\Post;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FCM HTTP v1 notification contract for POST /{username} (senMessageToUser).
 *
 * Uses Http::fake — no real Firebase API calls.
 */
class FcmNotificationTest extends TestCase
{
    private const TITLE = 'لديك رسالة جديدة';

    private const BODY = 'لديك رسالة جديدة';

    private const ICON = 'img/icon.png';

    private const IMAGE = 'img/d.png';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'firebase.project_id' => 'test-project',
            'firebase.client_email' => 'firebase@test.iam.gserviceaccount.com',
            'firebase.private_key' => file_get_contents(__DIR__.'/../fixtures/firebase_test_private_key.pem'),
            'firebase.timeout' => 5,
        ]);
    }

    private function createRecipient(array $overrides = []): User
    {
        $defaults = [
            'name' => 'Recipient',
            'email' => 'recipient@example.com',
            'username' => 'recipient',
            'password' => Hash::make('password'),
            'active' => 1,
            'is_public' => 1,
            'accept_posts' => 1,
            'show_zwar' => 1,
            'visitors' => 0,
        ];

        $user = User::create(array_merge($defaults, array_intersect_key($overrides, array_flip(array_keys($defaults)))));

        $user->active_notification = $overrides['active_notification'] ?? 1;
        $user->token_notification = array_key_exists('token_notification', $overrides)
            ? $overrides['token_notification']
            : 'device-token-abc';
        $user->save();

        return $user->fresh();
    }

    private function fakeFirebaseHttp(?callable $onFcmRequest = null): void
    {
        Http::fake(function ($request) use ($onFcmRequest) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response([
                    'access_token' => 'test-oauth-access-token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                ]);
            }

            if (str_contains($request->url(), 'fcm.googleapis.com/v1/projects/')) {
                if ($onFcmRequest !== null) {
                    $onFcmRequest($request);
                }

                return Http::response(['name' => 'projects/test-project/messages/0']);
            }

            return Http::response([], 404);
        });
    }

    private function postMessage(User $recipient, string $message = 'Hello there'): \Illuminate\Testing\TestResponse
    {
        return $this->post('/any-username-segment', [
            'message' => $message,
            'user_id' => $recipient->id,
        ]);
    }

    private function assertFcmPayload(array $payload, string $expectedToken): void
    {
        $this->assertSame($expectedToken, $payload['message']['token'] ?? null);
        $this->assertSame(self::TITLE, $payload['message']['data']['title'] ?? null);
        $this->assertSame(self::BODY, $payload['message']['data']['body'] ?? null);
        $this->assertSame(self::ICON, $payload['message']['data']['icon'] ?? null);
        $this->assertSame(self::IMAGE, $payload['message']['data']['image'] ?? null);
    }

    public function test_notification_sent_when_active_and_token_exists(): void
    {
        $recipient = $this->createRecipient();
        $capturedPayload = null;

        $this->fakeFirebaseHttp(function ($request) use (&$capturedPayload) {
            $capturedPayload = $request->data();
        });

        $this->postMessage($recipient)
            ->assertRedirect()
            ->assertSessionHas('msg', 'تم إرسال الرسالة بنجاح , شكرا لك .');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'fcm.googleapis.com/v1/projects/test-project/messages:send');
        });

        $this->assertIsArray($capturedPayload);
        $this->assertFcmPayload($capturedPayload, 'device-token-abc');
    }

    public function test_notification_not_sent_when_active_notification_disabled(): void
    {
        $recipient = $this->createRecipient([
            'active_notification' => 0,
            'token_notification' => 'device-token-abc',
        ]);

        $this->fakeFirebaseHttp();

        $this->postMessage($recipient)->assertRedirect();

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'fcm.googleapis.com/v1/projects/');
        });
    }

    public function test_notification_not_sent_when_token_is_null(): void
    {
        $recipient = $this->createRecipient([
            'active_notification' => 1,
            'token_notification' => null,
        ]);

        $this->fakeFirebaseHttp();

        $this->postMessage($recipient)->assertRedirect();

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'fcm.googleapis.com/v1/projects/');
        });
    }

    public function test_correct_recipient_token_is_used(): void
    {
        $recipient = $this->createRecipient(['token_notification' => 'unique-token-xyz']);
        $capturedPayload = null;

        $this->fakeFirebaseHttp(function ($request) use (&$capturedPayload) {
            $capturedPayload = $request->data();
        });

        $this->postMessage($recipient);

        $this->assertFcmPayload($capturedPayload, 'unique-token-xyz');
    }

    public function test_exact_notification_title_body_icon_and_image(): void
    {
        $recipient = $this->createRecipient();
        $capturedPayload = null;

        $this->fakeFirebaseHttp(function ($request) use (&$capturedPayload) {
            $capturedPayload = $request->data();
        });

        $this->postMessage($recipient);

        $data = $capturedPayload['message']['data'];
        $this->assertSame(self::TITLE, $data['title']);
        $this->assertSame(self::BODY, $data['body']);
        $this->assertSame(self::ICON, $data['icon']);
        $this->assertSame(self::IMAGE, $data['image']);
    }

    public function test_message_saved_before_firebase_is_called(): void
    {
        $recipient = $this->createRecipient();
        $postExistedBeforeFcm = false;

        $this->fakeFirebaseHttp(function () use ($recipient, &$postExistedBeforeFcm) {
            $postExistedBeforeFcm = Post::where('user_id', $recipient->id)
                ->where('body', 'Saved first')
                ->exists();
        });

        $this->postMessage($recipient, 'Saved first');

        $this->assertTrue($postExistedBeforeFcm);
        $this->assertDatabaseHas('posts', [
            'user_id' => $recipient->id,
            'body' => 'Saved first',
            'type' => 0,
            'is_public' => 0,
        ]);
    }

    public function test_firebase_failure_does_not_prevent_message_success_flow(): void
    {
        $recipient = $this->createRecipient();

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response([
                    'access_token' => 'test-oauth-access-token',
                    'expires_in' => 3600,
                ]);
            }

            if (str_contains($request->url(), 'fcm.googleapis.com/v1/projects/')) {
                return Http::response(['error' => 'unavailable'], 503);
            }

            return Http::response([], 404);
        });

        $this->postMessage($recipient, 'Still saved')
            ->assertRedirect()
            ->assertSessionHas('msg', 'تم إرسال الرسالة بنجاح , شكرا لك .');

        $this->assertDatabaseHas('posts', [
            'user_id' => $recipient->id,
            'body' => 'Still saved',
        ]);
    }

    public function test_oauth_request_uses_service_account_jwt(): void
    {
        $recipient = $this->createRecipient();

        $this->fakeFirebaseHttp();

        $this->postMessage($recipient);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return false;
            }

            return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && is_string($request['assertion'])
                && str_contains($request['assertion'], '.');
        });
    }

    public function test_fcm_request_uses_oauth_bearer_not_legacy_key(): void
    {
        $recipient = $this->createRecipient();

        $this->fakeFirebaseHttp();

        $this->postMessage($recipient);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'fcm.googleapis.com/v1/projects/test-project/messages:send')) {
                return false;
            }

            $auth = $request->header('Authorization')[0] ?? '';

            return str_starts_with($auth, 'Bearer test-oauth-access-token')
                && ! str_contains($auth, 'Key=');
        });

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'fcm.googleapis.com/fcm/send');
        });
    }
}
