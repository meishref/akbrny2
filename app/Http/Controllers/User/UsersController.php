<?php

namespace App\Http\Controllers\User;

use App\Post;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class UsersController extends Controller
{

   public function index(){

       $userId = Auth::user()->id;

       $allPosts = Post::where('user_id', $userId)
           ->with('answers')
           ->get();

       $posts = $allPosts->where('type', 0);
       $polls = $allPosts->where('type', 1);
       $posts_polls = $allPosts->sortByDesc('id')->values();

       Post::where('user_id', $userId)
           ->where('is_read', 0)
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
