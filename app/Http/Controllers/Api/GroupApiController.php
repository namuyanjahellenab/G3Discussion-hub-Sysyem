<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupStudent;
use Illuminate\Http\Request;

class GroupApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $groups = Group::withCount(['students as member_count'])
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
}