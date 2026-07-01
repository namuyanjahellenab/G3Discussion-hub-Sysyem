<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupStudent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $groups = Group::withCount(['students as member_count'])
            ->get()
            ->map(function ($group) use ($user) {
                $group->userJoined = GroupStudent::where('GroupID', $group->GroupID)
                    ->where('UserID', $user->UserID)
                    ->exists();

                return $group;
            });

        return view('groups.index', compact('groups'));
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

        return redirect()->route('groups.index');
    }

    public function leave(Group $group): RedirectResponse
    {
        $user = Auth::user();

        GroupStudent::where('GroupID', $group->GroupID)
            ->where('UserID', $user->UserID)
            ->delete();

        return redirect()->route('groups.index');
    }

    public function forum(Group $group)
    {
        return view('groups.forum', compact('group'));
    }
}
