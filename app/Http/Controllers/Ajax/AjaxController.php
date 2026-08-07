<?php

namespace App\Http\Controllers\Ajax;

use App\Answer;
use App\Post;
use App\User;
use Faker\Provider\Image;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Validator;
use File;

class AjaxController extends Controller
{

        function checkEmail(Request $request)
        {
            if($request->get('email'))
            {
                $email = $request->get('email');

                $data = DB::table("users")
                    ->where('email', $email)
                    ->count();
                if($data > 0)
                {
                    echo 'not_unique';
                }
                else
                {
                    echo 'unique';
                }
            }

            if($request->get('username'))
            {
                $username = $request->get('username');

                $data = DB::table("users")
                    ->where('username', $username)
                    ->count();
                if($data > 0)
                {
                    echo 'not_unique';
                }
                else
                {
                    echo 'unique';
                }
            }


        }

    function replyMessage(Request $request)
    {
        $message_reply_id=$request->get('message_reply_id');
        $reply=$request->get('reply');

        $rules = array(
            'reply'    =>  'required',
            'message_reply_id'=>  'required',
         );
        $error = Validator::make($request->all(), $rules);
        if($error->fails())
        {
            return response()->json(['errors' => $error->errors()->all()]);
        }

        $data = DB::table("posts")
            ->where('user_id',auth()->user()->id)
            ->where('id', $message_reply_id)
            ->count();

        $count_replay = DB::table("answers")
            ->where('user_id', auth()->user()->id)
            ->where('post_id', $message_reply_id)
            ->count();

        if($data ==0)
        {
            return response()->json(['errors' => trans('main.error_somethings')]);


        }
        elseif
        ($count_replay > 0){
            return response()->json(['errors' => trans('main.message_already_reply')]);


        }
        else
            {
                $insert= DB::table('answers')->insert(
                    ['post_id' =>$message_reply_id,
                        'user_id' =>auth()->user()->id ,
                        'body' =>$reply
                    ] );
                if($insert){
               return response()->json(['success' =>trans('main.message_done_reply')]);
                }

        }

    }

    function deleteReplyMessage(Request $request)
    {
        $reply_id=$request->get('reply_id');

       //  return response()->json(['errors' => $reply_id]);



        $count_replay = DB::table("answers")
            ->where('user_id', auth()->user()->id)
            ->where('id', $reply_id)
            ->count();

        if($count_replay == 1){

            $delete= DB::table('answers')
                ->where('id','=',$reply_id)
                ->where('user_id','=',auth()->user()->id)
                ->delete()
            ;


            if($delete){

                return response()->json(['success' =>trans('main.message_done_reply')]);
            }

            return response()->json(['errors' => trans('main.message_already_reply')]);


        }
        else

            {
                return response()->json(['errors' => trans('main.error_somethings')]);
        }

    }


   // editMessage show/hide
    function editMessage(Request $request)
    {
        $post_id=$request->get('post_id');
        $type=$request->get('type');
        $is_question=$request->get('is_question');


        $count_replay = DB::table("posts")
            ->where('user_id', auth()->user()->id)
            ->where('id', $post_id)
            ->count();
        if($count_replay == 1){
            $is_public=0;
            if($type==1){
                $is_public=0;
            }else{
                $is_public=1;
            }



            if($is_question==1){
                $update= DB::table('posts')
                    ->where('id','=',$post_id)
                    ->where('user_id','=',auth()->user()->id)
                    ->update(['is_active'=>$is_public]) ;

            }
            else{
                $update= DB::table('posts')
                    ->where('id','=',$post_id)
                    ->where('user_id','=',auth()->user()->id)
                    ->update(['is_public'=>$is_public]) ;

            }


            if($update){
                if($is_question==1){

                    if($type==1){
                        return response()->json(['success' =>trans('main.question_done_hide')]);
                    }else
                    {
                        return response()->json(['success' =>trans('main.question_done_show')]);

                    }
                }else{


                    if($type==1){
                        return response()->json(['success' =>trans('main.message_done_hide')]);
                    }else
                    {
                        return response()->json(['success' =>trans('main.message_done_show')]);

                    }
                }



            }
        }
        else
            {
                return response()->json(['errors' => trans('main.error_somethings')]);
        }

    }

    //delete Msg
    function deleteMessage(Request $request)
    {
        $post_id=$request->get('post_id');

        //  return response()->json(['errors' => $reply_id]);



        $count_replay = DB::table("posts")
            ->where('user_id', auth()->user()->id)
            ->where('id', $post_id)
            ->count();

        if($count_replay == 1){

            $delete= DB::table('posts')
                ->where('id','=',$post_id)
                ->where('user_id','=',auth()->user()->id)
                ->delete()
            ;

            if($delete){
                return response()->json(['success' =>trans('main.message_deleted_done')]);
            }



        }
        else

        {
            return response()->json(['errors' => trans('main.error_somethings')]);
        }

    }


    function addQuestion(Request $request)
    {
        $question=$request->get('question');
        $option1=$request->get('option1');
        $option2=$request->get('option2');
        $option3=$request->get('option3');
        $option4=$request->get('option4');

        $rules = array(
            'question'    =>  'required',
            'option1'=>  'required',
            'option2'=>  'required',
        );

        $error = Validator::make($request->all(), $rules);
        if($error->fails())
        {
            return response()->json(['errors' => $error->errors()->all()]);
        }

        $insert= DB::table('posts')->insert(
            ['body' =>$question,
                'user_id' =>auth()->user()->id ,
                'answer1' =>$option1,
                'answer2' =>$option2,
                'answer3' =>$option3,
                'answer4' =>$option4,
                'type' =>1,
                'is_active' =>1,
                'is_public' =>1,
              ] );

        if($insert){
            return response()->json(['success' =>trans('main.question_done_add')]);
        }

    }


    function userEditInfo(Request $request){

        $name=$request->get('name');
        $email=$request->get('email');
        $text_profile=$request->get('text_profile');

        $rules = array(
            'name'    =>  'required|min:1',
            'email'=>'required|email ',
            'text_profile'=>'max:50',
         );

        $error = Validator::make($request->all(), $rules);
        if($error->fails())
        {
            return response()->json(['errors' => $error->errors()->all()]);
        }


        $check_email= DB::table("users")
            ->where('id','!=',auth()->user()->id)
            ->where('email','=',$email)
            ->count();


        if($check_email>0){
            return response()->json(['errors' => trans('main.user_email_token')]);

        }
        else{

            $update= DB::table('users')
                ->where('id','=',auth()->user()->id)
                ->update(
                    [
                        'name'=>$name,
                        'email'=>$email,
                        'text_profile'=>$text_profile,
                    ]) ;

            if($update){

                return response()->json(['success' => trans('main.user_done_edit_info')]);


            }


        }



    }






    /* Chane password */
    public function userChangePassword(Request $request){
        if (!(Hash::check($request->get('current-password'), Auth::user()->password))) {
            // The passwords matches
            return response()->json(['errors' => 'كلمة المرور الحالية غير صحيحة .']);


        }

        if(strcmp($request->get('current-password'), $request->get('password')) == 0){
            //Current password and new password are same
            return response()->json(['errors' => 'لايمكن تعديل كلمة المرور لانها مستخدمة من قبل']);

         }



        $rules = array(
            'current-password' => 'required',
            'password' => 'required|string|min:6|confirmed',
        );

        $error = Validator::make($request->all(), $rules);

        if($error->fails())
        {
            return response()->json(['errors' => $error->errors()->all()]);
        }




        $user = Auth::user();
        $user->password = bcrypt($request->get('password'));
        $user->save();

        return response()->json(['success' => 'تم تغيير كلمة المرور بنجاح']);



    }


    public function check_base64_image($base64) {
        $img = imagecreatefromstring(base64_decode($base64));
        if (!$img) {
            return false;
        }

        imagepng($img, 'tmp.png');
        $info = getimagesize('tmp.png');

        unlink('tmp.png');

        if ($info[0] > 0 && $info[1] > 0 && $info['mime']) {
            return true;
        }

        return false;
    }

    // change password
    function userChangeImage(Request $request){
        $image=$request->get('image');
        $image_array_1 = explode(";", $image);
        $image_array_2 = explode(",", $image_array_1[1]);
        $base64 = base64_decode($image_array_2[1]);

        $user_id =Auth::user()->id;


        $image_name = "img_".time().$user_id.".png";
        $path=public_path().'/images/profile/';


        $old_image=Auth::user()->image;





        $success = file_put_contents($path.$image_name, $base64);

        if($success){



            $user = Auth::user();
            $user->image = $image_name;
            $user->save();


            File::delete($path.$old_image);

            $size_in_bytes = (int) (strlen(rtrim($base64, '=')) * 3 / 4);
            $size_in_kb    = (int) strlen($base64);

           // $uri = 'data://application/octet-stream;base64,' . $base64;
            $sizee=  '';


            return response()->json(['success' =>' تم تحديث الصورة بنجاح'.$sizee]);
        }
        else{

            return response()->json(['error' =>'حدث خطاء , الرجاء المحاولة لاحقا']);


        }


    }


    public function userEditSettings(Request $request){


        $user = Auth::user();
        $user->is_public= $request->has('is_public');
        $user->accept_posts = $request->has('accept_posts');
        $user->show_zwar = $request->has('show_zwar');
        $user->active_notification = $request->has('active_notification');
        $user->save();

        return response()->json(['success' =>'تم حفظ الاعدادات بنجاح']);




    }



    public function userEditSocial(Request $request){

        $rules = array(
            'web'    =>  'nullable|url',
            'twitter'    =>  'nullable|url',
            'instagram'    =>  'nullable|url',
            'youtube'    =>  'nullable|url',
            'snapchat'    =>  'nullable|url',
            'telegram'    =>  'nullable|url',
            'facebook'    =>  'nullable|url',
            'linkedin'    =>  'nullable|url',
        );

        $error = Validator::make($request->all(), $rules);
        if($error->fails())
        {
            return response()->json(['errors' => $error->errors()->all()]);
        }



        $user = Auth::user();
        $user->web= $request->get('web');
        $user->twitter= $request->get('twitter');
        $user->instagram= $request->get('instagram');
        $user->youtube= $request->get('youtube');
        $user->snapchat= $request->get('snapchat');
        $user->telegram= $request->get('telegram');
        $user->facebook= $request->get('facebook');
        $user->linkedin= $request->get('linkedin');


        $user->save();

        return response()->json(['success' =>'تم حفظ الاعدادات بنجاح']);




    }




    public function  profileSendVote(Request $request){

        $post_id=$request->input('post_id');
        $select_id=$request->input('select_id');

        if(auth()->check()){

        $check_username= DB::table("users")
            ->where('id','=', auth()->user()->id)
            ->where('active','=',1)
            ->count();


        $check_posts= DB::table("posts")
            ->where('id','=', $post_id)
            ->where('type','=',1)
            ->count();


        if ($check_username==1 && $check_posts==1){


            $posts_reply_count= DB::table("answers")
                ->where('user_id','=', auth()->user()->id)
                ->where('post_id','=',$post_id)
                ->count();


            if($posts_reply_count>0){
                // already reply
                return response()->json(['error' =>'لقد قمت بالتصويت مسبقا']);





            }

            else
                {

                    $answer = new Answer();
                    $answer->post_id =$post_id;
                    $answer->body =$select_id;
                    $answer->user_id =auth()->user()->id;
                    $answer->save();

                    return response()->json(['success' =>'تم التصويت بنجاح']);





                }





        }
        else{

            return response()->json(['error' =>'حدث خطاء , حاول لاحقا']);


        }


    }else{
            return response()->json(['error' =>'يجب عليك التسجيل اولا للتصويت  ... ']);

        }



    }


    public function siteSearch(Request $request){

        $query= $request->get('query');


        if($query != '')
        {
            $data = DB::table('users')
                ->where('name', 'like', '%'.$query.'%')
                ->orWhere('username', 'like', '%'.$query.'%')
                ->where('is_public', '=', 1)
                ->get();

        }
        $output='';
        $total_row = count($data) ;

       // $output.=' '.$total_row;


        if($total_row > 0)
        {

            $output .= '<ul class="list-group">';
            foreach($data as $row)
            {
           $output .= '
            <li  class="list-group-item"><a  href="'.route('user.getUser',$row->username).'"><img src="'  .asset($row->image?'images/profile/'.$row->image:'img/avatar2.png' ).'" height="30" width="30"/> '.$row->name.'</a></li> ';
            }

            $output .= '</ul>';




            return response()->json(['success' =>$output]);


        }
        else

           {

                $output.='لاتوجد نتائج لبحثك' ;
                return response()->json(['success' =>$output]);

            }










    }


    public function saveNotificationToken(Request $request)
    {

        $token = $request->get('currentToken');

        if(auth()->check()) {
            $user = Auth::user();


            if($user->token_notification!= $token){
                $user->token_notification = $token;
                $user->save();
                return response()->json(['success' =>'done save token']);


            }else{
                return response()->json(['success' =>'token already saved']);


            }




            return response()->json(['success2' =>$token]);






        }
        else
            {

            return response()->json(['errors' =>'not login']);

        }



        }






}
