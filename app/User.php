<?php

namespace App;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Columns aligned with production metadata (users table).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_pass',
        'username',
        'image',
        'text_profile',
        'ip_address',
        'visitors',
        'active',
        'is_public',
        'accept_posts',
        'show_zwar',
        'active_notification',
        'token_notification',
        'android_token',
        'web',
        'twitter',
        'instagram',
        'youtube',
        'snapchat',
        'telegram',
        'facebook',
        'linkedin',
        'tiktok',
        'words_block',
    ];

    protected $hidden = [
        'password',
        'user_pass',
        'remember_token',
        'token_notification',
        'android_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_public' => 'boolean',
            'accept_posts' => 'boolean',
            'show_zwar' => 'boolean',
            'active_notification' => 'boolean',
            'visitors' => 'integer',
            'active' => 'integer',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Device push tokens stored in production `notifications` table.
     */
    public function deviceNotifications()
    {
        return $this->hasMany(Notification::class);
    }
}
