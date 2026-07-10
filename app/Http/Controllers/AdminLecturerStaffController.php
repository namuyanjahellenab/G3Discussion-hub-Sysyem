<?php

namespace App\Http\Controllers;

use App\Models\LecturerStaffId;
use Illuminate\Http\Request;

class AdminLecturerStaffController extends Controller
{
    public function index()
    {
        $staffIds = LecturerStaffId::with('linkedUser')->orderByDesc('CreatedAt')->get();

        return view('admin.lecturer-staff.index', compact('staffIds'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'StaffIDNumber' => 'required|string|max:50|unique:LecturerStaffIDs,StaffIDNumber',
        ]);

        LecturerStaffId::create([
            'StaffIDNumber' => $validated['StaffIDNumber'],
            'IsUsed' => false,
        ]);

        return redirect()->route('admin.lecturer-staff.index')
            ->with('success', 'Staff ID added successfully.');
    }

    public function destroy(LecturerStaffId $staffId)
    {
        if ($staffId->IsUsed) {
            return redirect()->route('admin.lecturer-staff.index')
                ->with('error', 'Cannot delete a staff ID that has already been used.');
        }

        $staffId->delete();

        return redirect()->route('admin.lecturer-staff.index')
            ->with('success', 'Staff ID removed successfully.');
    }
}