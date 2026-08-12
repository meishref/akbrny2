<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/user';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('throttle:login')->only('login');
    }

    public function username()
    {
        $login = request()->input('email', '');

        if ($login === '') {
            return 'email';
        }

        $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        request()->merge([$fieldType => $login]);

        return $fieldType;
    }
}
