<?php

namespace Database\Factories;

use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $username = Str::lower($this->faker->unique()->userName());

        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'user_pass' => null,
            'username' => $username,
            'image' => null,
            'text_profile' => $this->faker->optional()->sentence(6),
            'ip_address' => '127.0.0.1',
            'visitors' => $this->faker->numberBetween(0, 200),
            'active' => 1,
            'is_public' => 1,
            'accept_posts' => 1,
            'show_zwar' => 1,
            'active_notification' => 1,
            'token_notification' => null,
            'android_token' => null,
            'web' => null,
            'twitter' => null,
            'instagram' => null,
            'youtube' => null,
            'snapchat' => null,
            'telegram' => null,
            'facebook' => null,
            'linkedin' => null,
            'tiktok' => null,
            'words_block' => null,
            'remember_token' => Str::random(10),
        ];
    }
}
