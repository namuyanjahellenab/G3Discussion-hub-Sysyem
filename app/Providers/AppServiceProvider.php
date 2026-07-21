<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use App\Models\GroupStudent;
use App\Models\Post;
use App\Models\Reply;
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
        // Avatar-circle initials used throughout the app: first letter of
        // the first name + first letter of the last name (e.g. "Muyingo
        // Star" -> "MS"), not just the first word - single-word names fall
        // back to that one letter.
        Str::macro('initials', function (?string $name): string {
            $parts = array_values(array_filter(preg_split('/\s+/', trim((string) $name))));
            if (empty($parts)) {
                return '?';
            }
            $letters = mb_strtoupper(mb_substr($parts[0], 0, 1));
            if (count($parts) > 1) {
                $letters .= mb_strtoupper(mb_substr(end($parts), 0, 1));
            }
            return $letters;
        });

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

        // This composer runs on EVERY page for every student/lecturer/admin
        // (the sidebar is in the shared layout), so a page whose route
        // doesn't already resolve a group - most of them - fell through to
        // a fresh GroupStudent query every single navigation. That's the
        // one thing this composer computes that isn't already free from the
        // route, so it's the one thing worth caching. A short TTL keeps a
        // just-joined group showing up in the sidebar promptly rather than
        // trading this speedup for a stale "Group Chat" link.
        if (!$currentGroupId && $userId) {
            $currentGroupId = Cache::remember(
                "sidebar_current_group:{$userId}",
                60,
                fn () => GroupStudent::where('UserID', $userId)->where('Status', 'active')->value('GroupID')
            );
        }

        $view->with('currentGroupId', $currentGroupId);
    });

        View::composer('layouts.sidebar-admin', function ($view) {
            // Same reasoning - every admin page recomputed this from two
            // full-table-ish COUNT queries on every navigation. The count
            // only needs to be "recent enough" for a sidebar badge, not
            // live-accurate to the second.
            $pendingFlagCount = Cache::remember(
                'sidebar_pending_flag_count',
                60,
                fn () => Post::where('IsFlagged', true)->count() + Reply::where('IsFlagged', true)->count()
            );

            $view->with('pendingFlagCount', $pendingFlagCount);
        });

        RateLimiter::for('forum-posts', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
