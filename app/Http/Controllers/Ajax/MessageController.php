<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messages,
    ) {}

    public function replyMessage(Request $request)
    {
        $error = Validator::make($request->all(), [
            'reply' => 'required',
            'message_reply_id' => 'required',
        ]);

        if ($error->fails()) {
            return response()->json(['errors' => $error->errors()->all()]);
        }

        return response()->json($this->messages->reply(
            auth()->user()->id,
            (int) $request->get('message_reply_id'),
            $request->get('reply'),
        ));
    }

    public function deleteReplyMessage(Request $request)
    {
        return response()->json($this->messages->deleteReply(
            auth()->user()->id,
            (int) $request->get('reply_id'),
        ));
    }

    public function editMessage(Request $request)
    {
        return response()->json($this->messages->toggleVisibility(
            auth()->user()->id,
            (int) $request->get('post_id'),
            (int) $request->get('type'),
            (int) $request->get('is_question'),
        ));
    }

    public function deleteMessage(Request $request)
    {
        return response()->json($this->messages->deletePost(
            auth()->user()->id,
            (int) $request->get('post_id'),
        ));
    }
}
