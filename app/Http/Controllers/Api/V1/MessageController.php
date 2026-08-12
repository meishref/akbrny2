<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messages,
    ) {}

    /**
     * POST /api/v1/messages/reply
     */
    public function reply(Request $request)
    {
        $error = Validator::make($request->all(), [
            'reply' => 'required',
            'message_reply_id' => 'required|integer',
        ]);

        if ($error->fails()) {
            return response()->json(['errors' => $error->errors()->all()], 422);
        }

        $result = $this->messages->reply(
            auth()->id(),
            (int) $request->input('message_reply_id'),
            $request->input('reply'),
        );

        return response()->json($result, isset($result['errors']) ? 422 : 200);
    }
}
