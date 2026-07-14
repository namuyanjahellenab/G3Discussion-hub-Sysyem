<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\GroupStudent;
use App\Models\Topic;

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

        // Prefer whichever group the current page is actually scoped to
        // (group forum, a topic within it, or the chat itself), so the
        // sidebar's Group Chat link follows you instead of always pointing
        // at one fixed group.
        $currentGroupId = null;

        $routeGroup = request()->route('group');
        $routeGroupId = request()->route('groupId');
        $routeTopic = request()->route('topic');

        if ($routeGroup) {
            $currentGroupId = is_object($routeGroup) ? $routeGroup->GroupID : $routeGroup;
        } elseif ($routeGroupId) {
            $currentGroupId = $routeGroupId;
        } elseif ($routeTopic) {
            $currentGroupId = is_object($routeTopic) ? $routeTopic->GroupID : Topic::find($routeTopic)?->GroupID;
        }

        if (!$currentGroupId) {
            $currentGroupId = $userId
                ? GroupStudent::where('UserID', $userId)->where('Status', 'active')->value('GroupID')
                : null;
        }

        $view->with('currentGroupId', $currentGroupId);
    });
        RateLimiter::for('forum-posts', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
