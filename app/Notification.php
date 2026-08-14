<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * FCM / push device tokens (production table: notifications).
 *
 * Not Laravel's Illuminate\Notifications\DatabaseNotification.
 */
class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'token',
        'device',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
