<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LecturerStudentController extends Controller
{
    /**
     * Lecturer picks a group; the roster shown is a fresh query every load
     * (no Cache::remember, unlike Group::activeMembers()) so a lecturer who
     * just registered — or whose group's membership just changed — always
     * sees what's in the database right now, not a stale snapshot.
     */
    public function index(Request $request)
    {
        abort_unless(in_array(Auth::user()->Role, ['Lecturer', 'Administrator'], true), 403);

        $groups = Group::orderBy('GroupName')
            ->withCount(['students as member_count' => fn ($q) => $q->where('Status', 'active')
                ->whereHas('user', fn ($q2) => $q2->where('Role', 'Student'))])
            ->get();

        $selectedGroupId = $request->integer('group') ?: optional($groups->first())->GroupID;

        $students = $selectedGroupId ? $this->rosterFor($selectedGroupId) : collect();

        return view('lecturer.students', compact('groups', 'selectedGroupId', 'students'));
    }

    /**
     * JSON snapshot of a group's roster, polled from the browser so the page
     * reflects join/leave/status changes made elsewhere without a manual
     * reload. Same live query as index() — no caching involved.
     */
    public function poll(Request $request)
    {
        abort_unless(in_array(Auth::user()->Role, ['Lecturer', 'Administrator'], true), 403);

        $request->validate([
            'group' => 'required|integer|exists:Group,GroupID',
        ]);

        $students = $this->rosterFor((int) $request->query('group'));

        return response()->json([
            'success' => true,
            'count' => $students->count(),
            'students' => $students->map(fn ($membership) => [
                'id' => $membership->user?->UserID,
                'name' => $membership->user?->UserName ?? $membership->user?->Handle ?? 'Unknown',
                'email' => $membership->user?->Email,
                'account_status' => $membership->user?->Status,
                'membership_status' => $membership->Status,
                'last_active' => optional($membership->user?->LastActive)?->diffForHumans(),
                'joined_at' => optional($membership->CreatedAt)?->format('M j, Y'),
            ])->values(),
        ]);
    }

    private function rosterFor(int $groupId)
    {
        return GroupStudent::where('GroupID', $groupId)
            ->whereHas('user', fn ($q) => $q->where('Role', 'Student'))
            ->with('user')
            ->get()
            ->sortBy(fn ($m) => $m->user?->UserName ?? '')
            ->values();
    }
}
