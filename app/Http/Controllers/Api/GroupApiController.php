<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Post;
use App\Services\GroupBrowseService;
use Illuminate\Http\Request;

class GroupApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $groups = Group::withCount(['students as member_count'])
            ->get()
            ->map(function ($group) use ($user) {
                $membership = GroupStudent::where('GroupID', $group->GroupID)
                    ->where('UserID', $user->UserID)
                    ->first();

                $hasNew = false;
                if ($membership) {
                    $postsQuery = Post::whereHas('topic', function ($q) use ($group) {
                        $q->where('GroupID', $group->GroupID);
                    });

                    $hasNew = $membership->LastViewedAt === null
                        ? $postsQuery->exists()
                        : $postsQuery->where('CreatedAt', '>', $membership->LastViewedAt)->exists();
                }

                return [
                    'id' => $group->GroupID,
                    'name' => $group->GroupName,
                    'member_count' => $group->member_count,
                    'is_member' => $membership !== null,
                    'has_new' => $hasNew,
                ];
            });

        return response()->json($groups);
    }

    // Mirrors GroupSelectionController::index() (the "Select a Discussion
    // Group" onboarding screen) - the same fixed 5-course list and order,
    // not every group in the system like index() above.
    public function forSelection(Request $request)
    {
        $user = $request->user();
        $groupNames = [
            'Algorithms',
            'Databases',
            'Software Engineering',
            'Networks',
            'data structures and algorithms',
        ];

        $groups = Group::withCount(['students as member_count'])
            ->whereIn('GroupName', $groupNames)
            ->orderByRaw("FIELD(GroupName, 'Algorithms', 'Databases', 'Software Engineering', 'Networks')")
            ->get()
            ->map(function ($group) use ($user) {
                return [
                    'id' => $group->GroupID,
                    'name' => $group->GroupName,
                    'member_count' => $group->member_count,
                    'is_member' => GroupStudent::where('GroupID', $group->GroupID)
                        ->where('UserID', $user->UserID)
                        ->exists(),
                ];
            });

        return response()->json($groups);
    }

    // Mirrors GroupController::index() (the web's "View Discussion Groups"
    // page reached from Dashboard's "See all") - Trending Groups ranked by
    // the ML gateway plus every other group, each with a derived course
    // code, via the shared GroupBrowseService so this can't drift from the
    // web the way the desktop's old hardcoded course-code list did.
    public function browse(Request $request)
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $browseService = app(GroupBrowseService::class);

        $trendingGroups = $search === '' ? $browseService->trendingGroups($user->UserID) : collect();
        $trendingGroupIds = $trendingGroups->pluck('GroupID');

        $groups = $browseService->browsableGroups($user->UserID, $search, $trendingGroupIds);

        $shape = fn ($group) => [
            'id' => $group->GroupID,
            'name' => $group->GroupName,
            'member_count' => $group->member_count,
            'course_code' => $group->CourseCode,
            'is_member' => $group->userJoined,
        ];

        return response()->json([
            'trending_groups' => $trendingGroups->map($shape)->values(),
            'groups' => $groups->map($shape)->values(),
        ]);
    }

    public function join(Request $request, Group $group)
    {
        $user = $request->user();

        GroupStudent::firstOrCreate([
            'GroupID' => $group->GroupID,
            'UserID' => $user->UserID,
        ], [
            'Status' => 'active',
        ]);

        return response()->json(['success' => true]);
    }

    public function leave(Request $request, Group $group)
    {
        $user = $request->user();

        GroupStudent::where('GroupID', $group->GroupID)
            ->where('UserID', $user->UserID)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function markViewed(Request $request, Group $group)
    {
        $user = $request->user();

        $membership = GroupStudent::where('GroupID', $group->GroupID)
            ->where('UserID', $user->UserID)
            ->first();

        if (!$membership) {
            return response()->json(['message' => 'Not a member of this group'], 403);
        }

        $membership->LastViewedAt = now();
        $membership->save();

        return response()->json(['success' => true]);
    }
}
