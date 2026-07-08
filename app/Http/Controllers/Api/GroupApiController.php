<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Post;
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
