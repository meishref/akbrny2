<?php

namespace App\Services;

use App\Post;

class QuestionService
{
    public function createPoll(int $userId, string $question, string $option1, string $option2, ?string $option3, ?string $option4): array
    {
        Post::create([
            'body' => $question,
            'user_id' => $userId,
            'answer1' => $option1,
            'answer2' => $option2,
            'answer3' => $option3,
            'answer4' => $option4,
            'type' => 1,
            'is_active' => 1,
            'is_public' => 1,
        ]);

        return ['success' => trans('main.question_done_add')];
    }
}
