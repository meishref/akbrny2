<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Services\PollVoteService;
use Illuminate\Http\Request;

class ProfileVoteController extends Controller
{
    public function __construct(
        private readonly PollVoteService $pollVotes,
    ) {}

    public function profileSendVote(Request $request)
    {
        if (! auth()->check()) {
            return response()->json(['error' => 'يجب عليك التسجيل اولا للتصويت  ... ']);
        }

        return response()->json($this->pollVotes->castVote(
            auth()->user()->id,
            (int) $request->input('post_id'),
            $request->input('select_id'),
        ));
    }
}
