<?php

use App\Http\Controllers\Ajax\AjaxController;
use App\Http\Controllers\HomeController;
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
Route::post('{username?}', [ProfileController::class, 'senMessageToUser'])->name('profile.senMessageToUser');

Route::post('ajax/email/check', [AjaxController::class, 'checkEmail'])->name('email_available.check');
Route::post('ajax/message/reply', [AjaxController::class, 'replyMessage'])->name('ajax.reply_message');
Route::post('ajax/message/delete_reply', [AjaxController::class, 'deleteReplyMessage'])->name('ajax.delete_reply_message');
Route::post('ajax/message/edit', [AjaxController::class, 'editMessage'])->name('ajax.edit_message');
Route::post('ajax/message/delete', [AjaxController::class, 'deleteMessage'])->name('ajax.delete_message');
Route::post('ajax/question/add', [AjaxController::class, 'addQuestion'])->name('ajax.question_add');
Route::post('ajax/user/edit_info', [AjaxController::class, 'userEditInfo'])->name('ajax.userEditInfo');
Route::post('ajax/user/changePassword', [AjaxController::class, 'userChangePassword'])->name('ajax.userChangePassword');
Route::post('ajax/user/ChangeImage', [AjaxController::class, 'userChangeImage'])->name('ajax.userChangeImage');
Route::post('ajax/user/EditSettings', [AjaxController::class, 'userEditSettings'])->name('ajax.userEditSettings');
Route::post('ajax/user/userEditSocial', [AjaxController::class, 'userEditSocial'])->name('ajax.userEditSocial');
Route::post('ajax/profile/profileSendVote', [AjaxController::class, 'profileSendVote'])->name('ajax.profileSendVote');
Route::post('ajax/user/saveNotificationToken', [AjaxController::class, 'saveNotificationToken'])->name('ajax.saveNotificationToken');
Route::get('ajax/site/search', [AjaxController::class, 'siteSearch'])->name('ajax.siteSearch');

Route::get('pages/contact', fn () => view('pages.contact'))->name('pages.contact');
Route::get('pages/privacy-policy', fn () => view('pages.privacy-policy'))->name('pages.privacy-policy');
Route::get('pages/terms', fn () => view('pages.terms'))->name('pages.terms');
