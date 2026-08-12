<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Services\QuestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuestionController extends Controller
{
    public function __construct(
        private readonly QuestionService $questions,
    ) {}

    public function addQuestion(Request $request)
    {
        $error = Validator::make($request->all(), [
            'question' => 'required',
            'option1' => 'required',
            'option2' => 'required',
        ]);

        if ($error->fails()) {
            return response()->json(['errors' => $error->errors()->all()]);
        }

        return response()->json($this->questions->createPoll(
            auth()->user()->id,
            $request->get('question'),
            $request->get('option1'),
            $request->get('option2'),
            $request->get('option3'),
            $request->get('option4'),
        ));
    }
}
