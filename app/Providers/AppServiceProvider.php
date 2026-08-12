<?php

namespace App\Providers;

use App\Post;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();

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

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(20)->by(
            $request->user()?->id ?: $request->ip()
        ));

        RateLimiter::for('votes', fn (Request $request) => Limit::perMinute(30)->by(
            $request->user()?->id ?: $request->ip()
        ));

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('notification-token', fn (Request $request) => Limit::perMinute(10)->by(
            $request->user()?->id ?: $request->ip()
        ));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by(
            $request->user()?->id ?: $request->ip()
        ));
    }
}
