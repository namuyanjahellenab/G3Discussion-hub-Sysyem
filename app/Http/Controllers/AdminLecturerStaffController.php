<?php

namespace App\Http\Controllers;

use App\Models\LecturerStaffId;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLecturerStaffController extends Controller
{
    public function index()
    {
        $staffIds = LecturerStaffId::with('linkedUser')->orderByDesc('CreatedAt')->get();

        $staffUsers = User::whereIn('Role', ['Lecturer', 'Staff', 'Administrator'])
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
        if ($user->PreviousRole !== null) {
            return redirect()->route('admin.lecturer-staff.index')
                ->with('error', $user->UserName . ' is already promoted.');
        }

        if (!in_array($user->Role, ['Lecturer', 'Staff'])) {
            return redirect()->route('admin.lecturer-staff.index')
                ->with('error', 'Only Lecturers or Staff can be promoted to Admin.');
        }

        // PreviousRole alone marks them "promoted" - Role is deliberately
        // left as-is (still 'Lecturer'/'Staff') rather than flipped to
        // 'Administrator' here. Role is what the sidebar (layouts/sidebar.
        // blade.php), the 'admin' middleware, and DashboardController::
        // index() all key off to decide which screen/nav a user currently
        // sees, so if promote() changed it immediately, a promoted lecturer
        // landing on their own lecturer dashboard (by design, see index())
        // would still get the ADMIN sidebar wrapped around it - every nav
        // link on their "lecturer" page would actually point back into the
        // admin section. Only DashboardController::switchToAdmin() (the
        // "Go to Admin Dashboard" button, an explicit user action) should
        // ever set Role to 'Administrator'.
        $user->update([
            'PreviousRole' => $user->Role,
        ]);

        Notification::create([
            'UserID' => $user->UserID,
            'Message' => 'You are promoted to be an admin',
            'Status' => false,
            'Type' => 'Role',
        ]);

        return redirect()->route('admin.lecturer-staff.index')
            ->with('success', $user->UserName . ' promoted to Admin.');
    }

    public function demote(User $user)
    {
        $currentUser = Auth::user();

        if ($user->UserID === $currentUser->UserID) {
            return redirect()->route('admin.lecturer-staff.index')
                ->with('error', 'You cannot demote yourself.');
        }

        // PreviousRole, not Role, is the check - Role only reflects whichever
        // side (Lecturer/Administrator) this specific user last toggled to
        // via DashboardController::switchToAdmin()/switchToLecturer(), which
        // has nothing to do with whether they're still promoted. Checking
        // Role here meant demote() would randomly fail with "not currently
        // an Admin" whenever the target happened to be sitting in their
        // Lecturer-mode toggle state at the moment an admin tried to demote
        // them - this is what "makes it work no matter how many times
        // promote/demote/toggle are repeated."
        if ($user->PreviousRole === null) {
            return redirect()->route('admin.lecturer-staff.index')
                ->with('error', 'This user is not currently an Admin.');
        }

        // A "main" admin is one who was never promoted (PreviousRole never
        // set) - only another main admin can demote them. A promoted admin
        // (PreviousRole set, e.g. a lecturer who was promoted) has no
        // authority over a main admin's account.
        if ($currentUser->PreviousRole !== null && $user->PreviousRole === null) {
            return redirect()->route('admin.lecturer-staff.index')
                ->with('error', 'You do not have permission to demote the main admin.');
        }

        $user->update([
            // Forces them back to their real original role regardless of
            // whichever mode (Lecturer/Administrator) they last toggled to -
            // a full demotion always wins over a leftover toggle state.
            'Role' => $user->PreviousRole,
            'PreviousRole' => null,
        ]);

        Notification::create([
            'UserID' => $user->UserID,
            'Message' => 'You have been restored to being a lecturer',
            'Status' => false,
            'Type' => 'Role',
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