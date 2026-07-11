<?php

namespace App\Http\Controllers;

use App\Models\LecturerStaffId;
use App\Models\User;
use Illuminate\Http\Request;

class AdminLecturerStaffController extends Controller
{
    public function index()
    {
        $staffIds = LecturerStaffId::with('linkedUser')->orderByDesc('CreatedAt')->get();

        $staffUsers = User::whereIn('Role', ['Lecturer', 'Staff', 'Admin'])
            ->orderBy('UserName')
            ->get();

        return view('admin.lecturer-staff.index', compact('staffIds', 'staffUsers'));
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

    public function promote(User $user)
    {
        if (!in_array($user->Role, ['Lecturer', 'Staff'])) {
            return redirect()->route('admin.lecturer-staff.index')
                ->with('error', 'Only Lecturers or Staff can be promoted to Admin.');
        }

        $user->update([
            'PreviousRole' => $user->Role,
            'Role' => 'Admin',
        ]);

        return redirect()->route('admin.lecturer-staff.index')
            ->with('success', $user->UserName . ' promoted to Admin.');
    }

    public function demote(User $user)
    {
        if ($user->Role !== 'Admin') {
            return redirect()->route('admin.lecturer-staff.index')
                ->with('error', 'This user is not currently an Admin.');
        }

        $user->update([
            'Role' => $user->PreviousRole ?? 'Lecturer',
            'PreviousRole' => null,
        ]);

        return redirect()->route('admin.lecturer-staff.index')
            ->with('success', $user->UserName . ' demoted from Admin.');
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