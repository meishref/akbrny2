<?php

namespace App\Http\Controllers\User;

use App\Post;
use App\User;
use Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{

    public function getUser($username){


        $check_username= DB::table("users")
            ->where('username','=',$username)
            ->where('active','=',1)
            ->count();

        if($check_username==1){




            $user= User::where('username','=',$username)->firstOrFail();
           $posts= Post::where('type','=',0)->where('user_id','=',$user->id)->latest()->get();
            $polls= Post::where('type','=',1)->where('user_id','=',$user->id)->latest()->get();

            $posts_polls= Post::where('user_id','=',$user->id)
                ->where('is_public','=',1)
                ->orderBy('id','desc')->get();


           // return $posts_polls;



            if(!session()->has($username)){
                session([$username => 'true']);
                $user->visitors=$user->visitors+1;
                $user->save();

            }



            return View('user.profile')->with(['user'=>$user,'posts'=>$posts,'posts_polls'=>$posts_polls,'polls'=>$polls]);

        }else{

            return 'user not found';
        }





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


        $check_username= DB::table("users")
            ->where('id','=',$user_id)
            ->where('active','=',1)
            ->count();


        if($check_username==1){


            $user= User::where('id','=',$user_id)->firstOrFail();



            $post = new Post();

            $post->user_id=$user_id;
            $post->body=$message;

            $post->is_public=0;
            $post->type=0;

            $post->save();




            if($user->active_notification==1 && $user->token_notification!=null){
                $this->sendNotification($user->token_notification,"لديك رسالة جديدة","لديك رسالة جديدة");
            }


             return back()->with( 'msg','تم إرسال الرسالة بنجاح , شكرا لك .');





        }else{

            return 'user not found';
        }


        //  return back()->withInput($request->input())->with( 'msg','هذا الفيديو موجود مسبقا');

        return $user_id;





    }


    public function sendNotification($notification_token,$title,$body){


        $SERVER_API_KEY='AAAArUfbw8Q:APA91bEwt2Rcf-DFdgisR6yfwzzskJBmocguDVRc3ie1N62KJDQiV6nfmPG-0Dz7kyrooIQ4JM9lNsRiHXBKevPwEctcgWyH86YKvfFhKxxz4B7vw23WoKkeoZKovOAo2tErZfU2TDP6';

        $header = [
            'Authorization: Key=' . $SERVER_API_KEY,
            'Content-Type: Application/json'
        ];

        $msg = [
            'title' =>$title,
            'body' => $body,
            'icon' => 'img/icon.png',
            'image' => 'img/d.png',
        ];

        $registrationIds[] = $notification_token;

        $payload = [
            'registration_ids' 	=> $registrationIds,
            'data'				=> $msg
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://fcm.googleapis.com/fcm/send",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode( $payload ),
            CURLOPT_HTTPHEADER => $header
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else
            {
            echo $response;
        }

    }


}
