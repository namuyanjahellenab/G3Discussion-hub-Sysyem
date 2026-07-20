<?php

namespace App\Http\Controllers;

use App\Models\StudentId;
use Illuminate\Http\Request;

class AdminStudentIdController extends Controller
{
    public function index()
    {
        $studentIds = StudentId::with('linkedUser')->orderByDesc('CreatedAt')->get();

        return view('admin.student-ids.index', compact('studentIds'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'StudentIDNumber' => 'required|string|max:50|unique:StudentIDs,StudentIDNumber',
        ]);

        StudentId::create([
            'StudentIDNumber' => $validated['StudentIDNumber'],
            'IsUsed' => false,
        ]);

        return redirect()->route('admin.student-ids.index')
            ->with('success', 'Student ID added successfully.');
    }

    public function destroy(StudentId $studentId)
    {
        if ($studentId->IsUsed) {
            return redirect()->route('admin.student-ids.index')
                ->with('error', 'Cannot delete a student ID that has already been used.');
        }

        $studentId->delete();

        return redirect()->route('admin.student-ids.index')
            ->with('success', 'Student ID removed successfully.');
    }
}
