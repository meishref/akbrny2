<?php

namespace App\Services;

use App\Answer;
use App\Post;

class MessageService
{
    public function reply(int $userId, int $messageReplyId, string $reply): array
    {
        if (! Post::where('user_id', $userId)->where('id', $messageReplyId)->exists()) {
            return ['errors' => trans('main.error_somethings')];
        }

        if (Answer::where('user_id', $userId)->where('post_id', $messageReplyId)->exists()) {
            return ['errors' => trans('main.message_already_reply')];
        }

        Answer::create([
            'post_id' => $messageReplyId,
            'user_id' => $userId,
            'body' => $reply,
        ]);

        return ['success' => trans('main.message_done_reply')];
    }

    public function deleteReply(int $userId, int $replyId): array
    {
        if (! Answer::where('user_id', $userId)->where('id', $replyId)->exists()) {
            return ['errors' => trans('main.error_somethings')];
        }

        $deleted = Answer::where('user_id', $userId)->where('id', $replyId)->delete();

        if ($deleted === 1) {
            return ['success' => trans('main.message_done_reply')];
        }

        return ['errors' => trans('main.message_already_reply')];
    }

    public function toggleVisibility(int $userId, int $postId, int $type, int $isQuestion): array
    {
        if (! Post::where('user_id', $userId)->where('id', $postId)->exists()) {
            return ['errors' => trans('main.error_somethings')];
        }

        $isPublic = $type === 1 ? 0 : 1;

        $updated = Post::where('id', $postId)
            ->where('user_id', $userId)
            ->update($isQuestion === 1 ? ['is_active' => $isPublic] : ['is_public' => $isPublic]);

        if (! $updated) {
            return ['errors' => trans('main.error_somethings')];
        }

        if ($isQuestion === 1) {
            return ['success' => trans($type === 1 ? 'main.question_done_hide' : 'main.question_done_show')];
        }

        return ['success' => trans($type === 1 ? 'main.message_done_hide' : 'main.message_done_show')];
    }

    public function deletePost(int $userId, int $postId): array
    {
        if (! Post::where('user_id', $userId)->where('id', $postId)->exists()) {
            return ['errors' => trans('main.error_somethings')];
        }

        $deleted = Post::where('id', $postId)->where('user_id', $userId)->delete();

        if ($deleted === 1) {
            return ['success' => trans('main.message_deleted_done')];
        }

        return ['errors' => trans('main.error_somethings')];
    }
}
