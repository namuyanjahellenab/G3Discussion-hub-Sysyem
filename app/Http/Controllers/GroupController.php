<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Notification;
use App\Models\Participation;
use App\Models\QuizResult;
use App\Models\StudentId;
use App\Models\StudentsLeft;
use App\Models\User;
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

        $membership = GroupStudent::where('GroupID', $group->GroupID)
            ->where('UserID', $user->UserID)
            ->first();

        if (!$membership) {
            return redirect()->route('groups.index');
        }

        // Snapshot everything the Statistics screen's group queries would
        // otherwise still be reading off of - this record is what lets
        // those queries exclude this student going forward without ever
        // touching (or losing) the underlying Post/Reply/QuizResult rows
        // themselves.
        $participation = Participation::where('GroupID', $group->GroupID)
            ->where('UserID', $user->UserID)
            ->first();

        $totalMarks = QuizResult::where('UserID', $user->UserID)
            ->whereHas('quiz', fn ($q) => $q->where('GroupID', $group->GroupID))
            ->sum('Score');

        StudentsLeft::create([
            'UserID' => $user->UserID,
            'UserName' => $user->UserName,
            'Email' => $user->Email,
            'StudentIDNumber' => StudentId::where('LinkedUserID', $user->UserID)->value('StudentIDNumber'),
            'GroupID' => $group->GroupID,
            'GroupName' => $group->GroupName,
            'TotalMarks' => $totalMarks,
            'PostCount' => $participation->PostCount ?? 0,
            'ReplyCount' => $participation->ReplyCount ?? 0,
            'ParticipationScore' => $participation->ParticipationScore ?? 0,
            'LeftAt' => now(),
        ]);

        // Admins need to know someone left; the remaining members of THIS
        // group get the same notice so the group itself isn't left wondering
        // where a member went.
        $message = "{$user->UserName} has left {$group->GroupName}.";

        foreach (User::where('Role', 'Administrator')->get() as $admin) {
            Notification::create([
                'UserID' => $admin->UserID,
                'Message' => $message,
                'Status' => false,
                'Type' => 'Group',
            ]);
        }

        $remainingMemberIds = GroupStudent::where('GroupID', $group->GroupID)
            ->where('UserID', '!=', $user->UserID)
            ->pluck('UserID');

        foreach ($remainingMemberIds as $memberId) {
            Notification::create([
                'UserID' => $memberId,
                'Message' => $message,
                'Status' => false,
                'Type' => 'Group',
            ]);
        }

        // Deleting the loaded model (not a bulk query delete) fires
        // GroupStudent's own deleted-event hook, which busts
        // Group::activeMembers()'s cache for this group.
        $membership->delete();

        return redirect()->route('groups.index');
    }

    public function forum(Group $group)
    {
        return view('groups.forum', compact('group'));
    }
}
