<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Services\NotificationTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationTokenService $notificationTokens,
    ) {}

    public function saveNotificationToken(Request $request)
    {
        if (! auth()->check()) {
            return response()->json(['errors' => 'not login']);
        }

        return response()->json($this->notificationTokens->saveToken(
            Auth::user(),
            $request->get('currentToken'),
        ));
    }
}
