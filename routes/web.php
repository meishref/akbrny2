<?php

use App\Http\Controllers\Ajax\EmailCheckController;
use App\Http\Controllers\Ajax\MessageController as AjaxMessageController;
use App\Http\Controllers\Ajax\NotificationController as AjaxNotificationController;
use App\Http\Controllers\Ajax\ProfileVoteController;
use App\Http\Controllers\Ajax\QuestionController;
use App\Http\Controllers\Ajax\SearchController as AjaxSearchController;
use App\Http\Controllers\Ajax\UserAccountController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\UsersController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes();

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::prefix('user')->middleware('auth')->group(function () {
    Route::get('', [UsersController::class, 'index'])->name('user.index');
    Route::get('settings', [UsersController::class, 'settings'])->name('user.settings');
    Route::get('notification', [UsersController::class, 'notification'])->name('user.notification');
});

Route::get('{username?}', [ProfileController::class, 'getUser'])->name('user.getUser');
Route::post('{username?}', [ProfileController::class, 'senMessageToUser'])
    ->middleware('throttle:messages')
    ->name('profile.senMessageToUser');

Route::prefix('ajax')->group(function () {
    Route::post('email/check', [EmailCheckController::class, 'checkEmail'])->name('email_available.check');

    Route::post('message/reply', [AjaxMessageController::class, 'replyMessage'])
        ->middleware('throttle:messages')
        ->name('ajax.reply_message');
    Route::post('message/delete_reply', [AjaxMessageController::class, 'deleteReplyMessage'])
        ->middleware('throttle:messages')
        ->name('ajax.delete_reply_message');
    Route::post('message/edit', [AjaxMessageController::class, 'editMessage'])
        ->middleware('throttle:messages')
        ->name('ajax.edit_message');
    Route::post('message/delete', [AjaxMessageController::class, 'deleteMessage'])
        ->middleware('throttle:messages')
        ->name('ajax.delete_message');

    Route::post('question/add', [QuestionController::class, 'addQuestion'])
        ->middleware('throttle:messages')
        ->name('ajax.question_add');

    Route::post('user/edit_info', [UserAccountController::class, 'userEditInfo'])->name('ajax.userEditInfo');
    Route::post('user/changePassword', [UserAccountController::class, 'userChangePassword'])->name('ajax.userChangePassword');
    Route::post('user/ChangeImage', [UserAccountController::class, 'userChangeImage'])->name('ajax.userChangeImage');
    Route::post('user/EditSettings', [UserAccountController::class, 'userEditSettings'])->name('ajax.userEditSettings');
    Route::post('user/userEditSocial', [UserAccountController::class, 'userEditSocial'])->name('ajax.userEditSocial');
    Route::post('user/saveNotificationToken', [AjaxNotificationController::class, 'saveNotificationToken'])
        ->middleware('throttle:notification-token')
        ->name('ajax.saveNotificationToken');

    Route::post('profile/profileSendVote', [ProfileVoteController::class, 'profileSendVote'])
        ->middleware('throttle:votes')
        ->name('ajax.profileSendVote');

    Route::get('site/search', [AjaxSearchController::class, 'siteSearch'])
        ->middleware('throttle:search')
        ->name('ajax.siteSearch');
});

Route::get('pages/contact', [PageController::class, 'contact'])->name('pages.contact');
Route::get('pages/privacy-policy', [PageController::class, 'privacyPolicy'])->name('pages.privacy-policy');
Route::get('pages/terms', [PageController::class, 'terms'])->name('pages.terms');
