<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PollVoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PollController extends Controller
{
    public function __construct(
        private readonly PollVoteService $pollVotes,
    ) {}

    /**
     * POST /api/v1/polls/vote
     */
    public function vote(Request $request)
    {
        $error = Validator::make($request->all(), [
            'post_id' => 'required|integer',
            'select_id' => 'required',
        ]);

        if ($error->fails()) {
            return response()->json(['errors' => $error->errors()->all()], 422);
        }

        $result = $this->pollVotes->castVote(
            auth()->id(),
            (int) $request->input('post_id'),
            $request->input('select_id'),
        );

        return response()->json($result, isset($result['error']) ? 422 : 200);
    }
}
