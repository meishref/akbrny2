<?php

namespace App\Services;

use App\User;

class EmailAvailabilityService
{
    /**
     * Registration availability check (plain-text contract for legacy AJAX).
     */
    public function checkPlainText(?string $email, ?string $username): string
    {
        $output = '';

        if ($email !== null && $email !== '') {
            $output .= User::where('email', $email)->exists() ? 'not_unique' : 'unique';
        }

        if ($username !== null && $username !== '') {
            $output .= User::where('username', $username)->exists() ? 'not_unique' : 'unique';
        }

        return $output;
    }

    public function emailTakenByOther(int $userId, string $email): bool
    {
        return User::where('id', '!=', $userId)->where('email', $email)->exists();
    }
}
