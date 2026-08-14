<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    /**
     * Columns aligned with production metadata (posts table).
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'body',
        'answer1',
        'answer2',
        'answer3',
        'answer4',
        'is_public',
        'is_read',
        'post_is_fav',
        'post_time',
        'ip',
        'is_active',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'is_public' => 'boolean',
            'is_read' => 'boolean',
            'is_active' => 'boolean',
            'post_is_fav' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}
