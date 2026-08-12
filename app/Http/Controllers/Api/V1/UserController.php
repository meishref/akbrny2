<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\UserAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct(
        private readonly UserAccountService $accounts,
    ) {}

    /**
     * PUT /api/v1/users/profile
     */
    public function updateProfile(Request $request)
    {
        $error = Validator::make($request->all(), [
            'name' => 'required|min:1',
            'email' => 'required|email',
            'text_profile' => 'max:50',
        ]);

        if ($error->fails()) {
            return response()->json(['errors' => $error->errors()->all()], 422);
        }

        $result = $this->accounts->updateProfile(
            auth()->id(),
            $request->input('name'),
            $request->input('email'),
            $request->input('text_profile'),
        );

        return response()->json($result, isset($result['errors']) ? 422 : 200);
    }
}
