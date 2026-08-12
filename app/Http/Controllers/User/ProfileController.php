<?php

namespace App\Http\Controllers\User;

use App\Answer;
use App\Jobs\SendFcmNotificationJob;
use App\Post;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{

    public function getUser($username){

        $user = User::where('username', '=', $username)
            ->where('active', '=', 1)
            ->first();

        if ($user === null) {
            return 'user not found';
        }

        $allPosts = Post::where('user_id', $user->id)
            ->with('answers')
            ->get();

        $posts = $allPosts->where('type', 0);
        $polls = $allPosts->where('type', 1);
        $posts_polls = $allPosts->where('is_public', 1)->sortByDesc('id')->values();

        $viewerAnswersByPostId = collect();
        if (auth()->check()) {
            $pollIds = $allPosts->where('type', 1)->pluck('id');
            if ($pollIds->isNotEmpty()) {
                $viewerAnswersByPostId = Answer::query()
                    ->where('user_id', auth()->id())
                    ->whereIn('post_id', $pollIds)
                    ->get()
                    ->keyBy('post_id');
            }
        }

        if(!session()->has($username)){
            session([$username => 'true']);
            $user->visitors=$user->visitors+1;
            $user->save();
        }

        return View('user.profile')->with([
            'user' => $user,
            'posts' => $posts,
            'posts_polls' => $posts_polls,
            'polls' => $polls,
            'viewerAnswersByPostId' => $viewerAnswersByPostId,
        ]);
    }


    public function senMessageToUser(Request $request){

        $message = $request->input('message');
        $user_id = $request->input('user_id');

        $messages = [
            'required' => 'الرسالة قصيرة جدا !',
            'min' => 'الرسالة قصيرة جدا !',
        ];



        $rules = array(
            'message' => 'required|min:2',
            'user_id' => 'required|numeric',
        );

        $error = Validator::make($request->all(), $rules,$messages);

        if($error->fails())
        {
           // return response()->json(['errors' => $error->errors()->all()]);
              return back()->withErrors( $error->errors()->all());

        }


        $user = User::where('id', '=', $user_id)
            ->where('active', '=', 1)
            ->first();

        if ($user === null) {
            return 'user not found';
        }

        $post = new Post();

        $post->user_id=$user_id;
        $post->body=$message;

        $post->is_public=0;
        $post->type=0;

        $post->save();

        if($user->active_notification==1 && $user->token_notification!=null){
            SendFcmNotificationJob::dispatch(
                $user->token_notification,
                'لديك رسالة جديدة',
                'لديك رسالة جديدة',
            );
        }

        return back()->with( 'msg','تم إرسال الرسالة بنجاح , شكرا لك .');
    }


    public function sendNotification($notification_token,$title,$body){

        SendFcmNotificationJob::dispatch(
            $notification_token,
            $title,
            $body,
            'img/icon.png',
            'img/d.png',
        );

    }


}
