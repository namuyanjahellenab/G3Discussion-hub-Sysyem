<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupStudent;
use App\Services\GroupBrowseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $search = trim((string) $request->query('search', ''));
        $browseService = app(GroupBrowseService::class);

        // Trending is a platform-wide popularity signal, not part of the
        // search results — showing it alongside a specific query reads as
        // "why is this trending group here, I searched for something else".
        $trendingGroups = $search === '' ? $browseService->trendingGroups($user->UserID) : collect();
        $trendingGroupIds = $trendingGroups->pluck('GroupID');

        $groups = $browseService->browsableGroups($user->UserID, $search, $trendingGroupIds);

        return view('groups.index', compact('groups', 'trendingGroups', 'search'));
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
