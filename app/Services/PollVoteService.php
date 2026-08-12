<?php

namespace App\Services;

use App\Answer;
use App\Post;
use App\User;

class PollVoteService
{
    public function castVote(int $userId, int $postId, string $selectId): array
    {
        if (! User::where('id', $userId)->where('active', 1)->exists()) {
            return ['error' => 'حدث خطاء , حاول لاحقا'];
        }

        if (! Post::where('id', $postId)->where('type', 1)->exists()) {
            return ['error' => 'حدث خطاء , حاول لاحقا'];
        }

        if (Answer::where('user_id', $userId)->where('post_id', $postId)->exists()) {
            return ['error' => 'لقد قمت بالتصويت مسبقا'];
        }

        Answer::create([
            'post_id' => $postId,
            'body' => $selectId,
            'user_id' => $userId,
        ]);

        return ['success' => 'تم التصويت بنجاح'];
    }
}
