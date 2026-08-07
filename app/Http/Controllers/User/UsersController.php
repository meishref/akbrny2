<?php

namespace App\Http\Controllers\User;

use App\Answer;
use App\Post;
use http\Message\Body;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UsersController extends Controller
{

   public function index(){


       $posts= Post::where('type','=',0)->where('user_id','=',Auth::user()->id)->latest()->get();


       $polls= Post::where('type','=',1)->where('user_id','=',Auth::user()->id)->latest()->get();

       $posts_polls= Post::where('user_id','=',Auth::user()->id)->orderBy('id','desc')->get();


     //  $question_count= Answer::where('type','=',1)->where('user_id','=',Auth::user()->id)->get()->count();

            Post::where('user_id', Auth::user()->id)
               ->where('is_read',0)
               ->update(['is_read' => 1]);

       return View('user.index')->with(['posts'=>$posts,'polls'=>$polls,'posts_polls'=>$posts_polls]);

    }

   public function settings(){
       return View('user.settings');
    }


   public function notification(){

       return View('user.notification');
    }



}
