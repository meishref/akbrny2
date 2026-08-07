<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Auth::routes();

//Route::get('/', 'HomeController@index')->name('index');
Route::get('/', 'HomeController@index')->name('home');



// route user
Route::prefix(['prefix'=>'user','middleware'=>'auth'],function () {


    Route::get('','User\UsersController@index')->middleware('auth')->name('user.index');
    Route::get('settings','User\UsersController@settings')->middleware('auth')->name('user.settings');


});

Route::group(['prefix'=>'user','middleware'=>'auth'],function() {


    Route::get('','User\UsersController@index')->middleware('auth')->name('user.index');
    Route::get('settings','User\UsersController@settings')->middleware('auth')->name('user.settings');
    Route::get('notification','User\UsersController@notification')->middleware('auth')->name('user.notification');


});


Route::get('{username?}','User\ProfileController@getUser')->name('user.getUser');
Route::post('{username?}','User\ProfileController@senMessageToUser')->name('profile.senMessageToUser');





// route ajax
Route::post('ajax/email/check', 'Ajax\AjaxController@checkEmail')->name('email_available.check');
Route::post('ajax/message/reply', 'Ajax\AjaxController@replyMessage')->name('ajax.reply_message');
Route::post('ajax/message/delete_reply', 'Ajax\AjaxController@deleteReplyMessage')->name('ajax.delete_reply_message');
Route::post('ajax/message/edit', 'Ajax\AjaxController@editMessage')->name('ajax.edit_message');
Route::post('ajax/message/delete', 'Ajax\AjaxController@deleteMessage')->name('ajax.delete_message');

Route::post('ajax/question/add', 'Ajax\AjaxController@addQuestion')->name('ajax.question_add');

Route::post('ajax/user/edit_info', 'Ajax\AjaxController@userEditInfo')->name('ajax.userEditInfo');
Route::post('ajax/user/changePassword', 'Ajax\AjaxController@userChangePassword')->name('ajax.userChangePassword');
Route::post('ajax/user/ChangeImage', 'Ajax\AjaxController@userChangeImage')->name('ajax.userChangeImage');
Route::post('ajax/user/EditSettings', 'Ajax\AjaxController@userEditSettings')->name('ajax.userEditSettings');
Route::post('ajax/user/userEditSocial', 'Ajax\AjaxController@userEditSocial')->name('ajax.userEditSocial');

Route::post('ajax/profile/profileSendVote', 'Ajax\AjaxController@profileSendVote')->name('ajax.profileSendVote');

Route::post('ajax/user/saveNotificationToken', 'Ajax\AjaxController@saveNotificationToken')->name('ajax.saveNotificationToken');


Route::get('ajax/site/search', 'Ajax\AjaxController@siteSearch')->name('ajax.siteSearch');


//Pages
Route::get('pages/contact', function (){
    return View('pages.contact');
})->name('pages.contact');

Route::get('pages/privacy-policy', function (){
    return View('pages.privacy-policy');
})->name('pages.privacy-policy');

Route::get('pages/terms', function (){
    return View('pages.terms');
})->name('pages.terms');

