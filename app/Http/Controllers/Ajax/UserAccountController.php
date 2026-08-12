<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Services\UserAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserAccountController extends Controller
{
    public function __construct(
        private readonly UserAccountService $accounts,
    ) {}

    public function userEditInfo(Request $request)
    {
        $error = Validator::make($request->all(), [
            'name' => 'required|min:1',
            'email' => 'required|email',
            'text_profile' => 'max:50',
        ]);

        if ($error->fails()) {
            return response()->json(['errors' => $error->errors()->all()]);
        }

        return response()->json($this->accounts->updateProfile(
            auth()->user()->id,
            $request->get('name'),
            $request->get('email'),
            $request->get('text_profile'),
        ));
    }

    public function userChangePassword(Request $request)
    {
        $error = Validator::make($request->all(), [
            'current-password' => 'required',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($error->fails()) {
            return response()->json(['errors' => $error->errors()->all()]);
        }

        return response()->json($this->accounts->changePassword(
            Auth::user(),
            $request->get('current-password'),
            $request->get('password'),
        ));
    }

    public function userChangeImage(Request $request)
    {
        return response()->json($this->accounts->updateProfileImage(
            Auth::user(),
            $request->get('image'),
        ));
    }

    public function userEditSettings(Request $request)
    {
        return response()->json($this->accounts->updateSettings(Auth::user(), [
            'is_public' => $request->has('is_public'),
            'accept_posts' => $request->has('accept_posts'),
            'show_zwar' => $request->has('show_zwar'),
            'active_notification' => $request->has('active_notification'),
        ]));
    }

    public function userEditSocial(Request $request)
    {
        $error = Validator::make($request->all(), [
            'web' => 'nullable|url',
            'twitter' => 'nullable|url',
            'instagram' => 'nullable|url',
            'youtube' => 'nullable|url',
            'snapchat' => 'nullable|url',
            'telegram' => 'nullable|url',
            'facebook' => 'nullable|url',
            'linkedin' => 'nullable|url',
        ]);

        if ($error->fails()) {
            return response()->json(['errors' => $error->errors()->all()]);
        }

        return response()->json($this->accounts->updateSocialLinks(Auth::user(), $request->only([
            'web', 'twitter', 'instagram', 'youtube', 'snapchat', 'telegram', 'facebook', 'linkedin',
        ])));
    }
}
