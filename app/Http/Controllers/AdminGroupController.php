<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\GroupBrowseService;
use Illuminate\Http\Request;

class AdminGroupController extends Controller
{
    public function index()
    {
        // CourseCode is now a persisted column (see the
        // add_course_code_to_groups_table migration) instead of something
        // recomputed on every read, so this can just select it like any
        // other field.
        $groups = Group::withCount(['students as member_count'])
            ->get();

        return view('admin.groups.index', compact('groups'));
    }

    public function store(Request $request, GroupBrowseService $groupBrowseService)
    {
        $validated = $request->validate([
            'GroupName' => 'required|string|max:100',
            'Description' => 'required|string|max:500',
        ]);

        $validated['CreatedBy'] = auth()->user()->UserName;

        $group = Group::create($validated);

        // CourseCode depends on the group's ID (see deriveGroupCode), so it
        // can only be computed once the row exists - saved right away here
        // instead of leaving it to be derived on every future read.
        $group->update(['CourseCode' => $groupBrowseService->deriveGroupCode($group)]);

        return redirect()->route('admin.groups.index')
            ->with('success', 'Group created successfully.');
    }

    public function update(Request $request, Group $group)
    {
        $validated = $request->validate([
            'GroupName' => 'required|string|max:100',
            'Description' => 'required|string|max:500',
        ]);

        $group->update($validated);

        return redirect()->route('admin.groups.index')
            ->with('success', 'Group updated successfully.');
    }
}