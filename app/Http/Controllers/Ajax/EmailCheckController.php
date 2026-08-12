<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Services\EmailAvailabilityService;
use Illuminate\Http\Request;

class EmailCheckController extends Controller
{
    public function __construct(
        private readonly EmailAvailabilityService $emailAvailability,
    ) {}

    public function checkEmail(Request $request)
    {
        return response($this->emailAvailability->checkPlainText(
            $request->get('email'),
            $request->get('username'),
        ));
    }
}
