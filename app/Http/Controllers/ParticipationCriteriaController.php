<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\ParticipationCriteria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ParticipationCriteriaController extends Controller
{
    // Lets a lecturer set how many points a post, a reply, and an accepted
    // answer are worth - either platform-wide (GroupID = null) or as an
    // override for one specific group. ParticipationService reads whatever
    // is saved here on every post/reply, so nothing else needs to change
    // for a new value to take effect.
    public function edit(Request $request)
    {
        abort_unless(Auth::user()->Role === 'Lecturer', 403);

        $groups = Group::orderBy('GroupName')->get();

        $groupId = $request->filled('group_id') ? (int) $request->input('group_id') : null;

        $criteria = $groupId
            ? ParticipationCriteria::where('GroupID', $groupId)->first()
            : ParticipationCriteria::whereNull('GroupID')->first();

        $criteria = $criteria ?? new ParticipationCriteria([
            'PointsPerPost' => 2,
            'PointsPerReply' => 1,
            'PointsPerAcceptedAnswer' => 0,
        ]);

        return view('lecturer.participation-criteria', compact('groups', 'groupId', 'criteria'));
    }

    public function update(Request $request)
    {
        abort_unless(Auth::user()->Role === 'Lecturer', 403);

        $validated = $request->validate([
            'GroupID' => 'nullable|integer|exists:Group,GroupID',
            'PointsPerPost' => 'required|numeric|min:0',
            'PointsPerReply' => 'required|numeric|min:0',
            'PointsPerAcceptedAnswer' => 'required|numeric|min:0',
        ]);

        $groupId = $validated['GroupID'] ?? null;

        $criteria = $groupId
            ? ParticipationCriteria::firstOrNew(['GroupID' => $groupId])
            : ParticipationCriteria::firstOrNew(['GroupID' => null]);

        $criteria->fill([
            'GroupID' => $groupId,
            'PointsPerPost' => $validated['PointsPerPost'],
            'PointsPerReply' => $validated['PointsPerReply'],
            'PointsPerAcceptedAnswer' => $validated['PointsPerAcceptedAnswer'],
        ])->save();

        return redirect()
            ->route('participation-criteria.edit', $groupId ? ['group_id' => $groupId] : [])
            ->with('status', 'Participation criteria saved.');
    }
}
