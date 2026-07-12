<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\GroupStudent;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.sidebar-lecturer', 'layouts.sidebar-student', 'layouts.sidebar-admin'], function ($view) {
        $userId = auth()->id();

        $currentGroupId = $userId
            ? GroupStudent::where('UserID', $userId)->where('Status', 'active')->value('GroupID')
            : null;

        $view->with('currentGroupId', $currentGroupId);
    });
        RateLimiter::for('forum-posts', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
