<?php

namespace App\Services;

use App\Notification;
use App\User;

class NotificationTokenService
{
    public function saveToken(User $user, ?string $token, ?string $device = null): array
    {
        if ($token === null || $token === '') {
            return ['errors' => 'not login'];
        }

        if ($user->token_notification === $token) {
            return ['success' => 'token already saved'];
        }

        $user->token_notification = $token;
        $user->save();

        $this->syncNotificationsTable($user->id, $token, $device);

        return ['success' => 'done save token'];
    }

    /**
     * Mirror token into production notifications table (multi-device history).
     * Primary FCM send path remains users.token_notification (legacy contract).
     */
    private function syncNotificationsTable(int $userId, string $token, ?string $device): void
    {
        Notification::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'token' => $token,
            ],
            [
                'device' => $device,
            ],
        );
    }
}
