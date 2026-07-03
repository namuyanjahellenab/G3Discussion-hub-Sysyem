<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupStudent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupSelectionController extends Controller
{
    public function index()
    {
        $groupNames = [
            'Algorithms',
            'Databases',
            'Software Engineering',
            'Networks',
            'data structures and algorithms',
        ];

        $user = Auth::user();

        $groups = Group::withCount(['students as member_count'])
            ->whereIn('GroupName', $groupNames)
            ->orderByRaw("FIELD(GroupName, 'Algorithms', 'Databases', 'Software Engineering', 'Networks')")
            ->get()
            ->map(function ($group) use ($user) {
                $group->userJoined = GroupStudent::where('GroupID', $group->GroupID)
                    ->where('UserID', $user->UserID)
                    ->exists();
                return $group;
            });

        return view('groups.select', compact('groups'))->with('showSidebar', false);
    }

    public function join(Group $group): RedirectResponse
    {
        $user = Auth::user();

        GroupStudent::firstOrCreate([
            'GroupID' => $group->GroupID,
            'UserID' => $user->UserID,
        ], [
            'Status' => 'active',
        ]);

        return redirect()->route('dashboard');
    }

    public function joinMultiple(Request $request): RedirectResponse
    {
        $request->validate([
            'groups' => 'required|array|min:1',
            'groups.*' => 'exists:Group,GroupID',
        ]);

        $user = Auth::user();

        foreach ($request->groups as $groupId) {
            GroupStudent::firstOrCreate([
                'GroupID' => $groupId,
                'UserID' => $user->UserID,
            ], [
                'Status' => 'active',
            ]);
        }

        return redirect()->route('dashboard');
    }
}
