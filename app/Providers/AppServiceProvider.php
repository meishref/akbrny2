<?php

namespace App\Providers;

use App\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            if (! app()->bound('view.num_message_unread')) {
                $count = 0;

                if (Auth::check()) {
                    $count = Post::query()
                        ->where('user_id', '=', auth()->id())
                        ->where('is_read', '=', 0)
                        ->where('type', '=', 0)
                        ->count();
                }

                app()->instance('view.num_message_unread', $count);
            }

            $view->with('num_message_unread', app('view.num_message_unread'));
        });
    }
}
