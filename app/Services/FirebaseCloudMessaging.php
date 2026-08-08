<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FirebaseCloudMessaging
{
    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function sendDataNotification(
        string $token,
        string $title,
        string $body,
        string $icon = 'img/icon.png',
        string $image = 'img/d.png',
    ): void {
        if (! $this->isConfigured()) {
            Log::warning('Firebase FCM skipped: credentials not configured.');

            return;
        }

        try {
            $accessToken = $this->fetchAccessToken();
            $this->sendMessage($accessToken, $token, $title, $body, $icon, $image);
        } catch (Throwable $e) {
            Log::warning('Firebase FCM send failed.', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function isConfigured(): bool
    {
        return filled(config('firebase.project_id'))
            && filled(config('firebase.client_email'))
            && filled(config('firebase.private_key'));
    }

    private function timeout(): int
    {
        return max(1, (int) config('firebase.timeout', 10));
    }

    private function fetchAccessToken(): string
    {
        $jwt = $this->createSignedJwt([
            'iss' => config('firebase.client_email'),
            'sub' => config('firebase.client_email'),
            'aud' => self::OAUTH_TOKEN_URL,
            'iat' => time(),
            'exp' => time() + 3600,
            'scope' => self::FCM_SCOPE,
        ]);

        $response = Http::timeout($this->timeout())
            ->asForm()
            ->post(self::OAUTH_TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Firebase OAuth token request failed.');
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Firebase OAuth token missing from response.');
        }

        return $token;
    }

    private function sendMessage(
        string $accessToken,
        string $deviceToken,
        string $title,
        string $body,
        string $icon,
        string $image,
    ): void {
        $url = sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            config('firebase.project_id'),
        );

        $response = Http::timeout($this->timeout())
            ->withToken($accessToken)
            ->acceptJson()
            ->post($url, [
                'message' => [
                    'token' => $deviceToken,
                    'data' => [
                        'title' => $title,
                        'body' => $body,
                        'icon' => $icon,
                        'image' => $image,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Firebase FCM HTTP v1 send request failed.');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function createSignedJwt(array $claims): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $signingInput = $header.'.'.$payload;

        $privateKey = openssl_pkey_get_private(config('firebase.private_key'));

        if ($privateKey === false) {
            throw new \RuntimeException('Firebase private key is invalid.');
        }

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new \RuntimeException('Firebase JWT signing failed.');
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
