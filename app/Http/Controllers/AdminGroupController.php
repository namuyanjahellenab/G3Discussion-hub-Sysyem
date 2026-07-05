<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;

class AdminGroupController extends Controller
{
    public function index()
    {
        $groups = Group::withCount(['students as member_count'])
            ->get();

        return view('admin.groups.index', compact('groups'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'GroupName' => 'required|string|max:100',
            'Description' => 'required|string|max:500',
        ]);

        $validated['CreatedBy'] = auth()->user()->UserName;

        Group::create($validated);

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