<?php

namespace App\Providers;

 use Illuminate\Support\Facades\DB;
 use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
 use Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        $num_message_unread =0;

        View::composer('*', function ($view) {

            $num_message_unread=0;
            if (Auth::check()) {

                $num_message_unread= DB::table("posts")
                    ->where('user_id','=',auth()->user()->id)
                    ->where('is_read','=',0)
                    ->where('type','=',0)
                    ->count();
              //  $project = Project::where('user_id', Auth::id())->count();
            }


            //$view->share($num_message_unread);
            $view->with('num_message_unread',$num_message_unread);


        });

    }


}
