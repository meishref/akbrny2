<?php

use App\Http\Controllers\Api\V1\MessageController as ApiMessageController;
use App\Http\Controllers\Api\V1\NotificationController as ApiNotificationController;
use App\Http\Controllers\Api\V1\PollController as ApiPollController;
use App\Http\Controllers\Api\V1\SearchController as ApiSearchController;
use App\Http\Controllers\Api\V1\UserController as ApiUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — v1 (JSON, for mobile / external clients)
|--------------------------------------------------------------------------
|
| Legacy frontend continues to use /ajax/* routes in routes/web.php.
| These endpoints share the same Service layer with zero contract change
| on existing AJAX URLs.
|
*/

Route::prefix('v1')->group(function () {
    Route::get('/search', [ApiSearchController::class, 'index']);

    Route::middleware('auth')->group(function () {
        Route::post('/messages/reply', [ApiMessageController::class, 'reply']);
        Route::post('/polls/vote', [ApiPollController::class, 'vote']);
        Route::post('/notifications/token', [ApiNotificationController::class, 'store']);
        Route::put('/users/profile', [ApiUserController::class, 'updateProfile']);
    });
});

// Legacy Laravel stub — preserved for backward compatibility.
Route::middleware('auth:api')->get('/user', function (\Illuminate\Http\Request $request) {
    return $request->user();
});
