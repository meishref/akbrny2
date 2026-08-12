<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationTokenService $notificationTokens,
    ) {}

    /**
     * POST /api/v1/notifications/token
     */
    public function store(Request $request)
    {
        $result = $this->notificationTokens->saveToken(
            Auth::user(),
            $request->input('currentToken'),
        );

        return response()->json($result, isset($result['errors']) ? 401 : 200);
    }
}
