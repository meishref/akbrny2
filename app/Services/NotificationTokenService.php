<?php

namespace App\Services;

use App\User;

class NotificationTokenService
{
    public function saveToken(User $user, ?string $token): array
    {
        if ($token === null || $token === '') {
            return ['errors' => 'not login'];
        }

        if ($user->token_notification === $token) {
            return ['success' => 'token already saved'];
        }

        $user->token_notification = $token;
        $user->save();

        return ['success' => 'done save token'];
    }
}
