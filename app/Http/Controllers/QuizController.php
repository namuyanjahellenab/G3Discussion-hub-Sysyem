<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Notification;
use App\Models\User;

class QuizController extends Controller
{
    // Loads the quiz scheduling screen
public function create()
{
    return view('quizzes.schedule');
}
    // ─── WEEK 1: Schedule a quiz ────────────────────────────────
    public function scheduleAssessment(Request $request)
    {
        // Step 1 — Validate all incoming data
        $request->validate([
            'Title'          => 'required|string|max:255',
            'StartTime'      => 'required|date',
            'Duration'       => 'required|integer|min:1',
            'TargetCategory' => 'required|string|max:100',
            'Questions'      => 'required|array|min:1',
            'Questions.*.QuestionText'  => 'required|string',
            'Questions.*.QuestionType' => 'required|in:MCQ,Open',
            'Questions.*.Options'      => 'nullable|array',
            'Questions.*.CorrectAnswer'=> 'nullable|string',
            'Questions.*.Marks'        => 'required|numeric|min:0',
        ]);

        // Step 2 — Save the quiz
        $quiz = Quiz::create([
            'LecturerID'     => auth()->user()->UserID,
            'Title'          => $request->Title,
            'StartTime'      => $request->StartTime,
            'Duration'       => $request->Duration,
            'TargetCategory' => $request->TargetCategory,
        ]);

        // Step 3 — Save the questions
        foreach ($request->Questions as $q) {
            Question::create([
                'QuizID'        => $quiz->QuizID,
                'QuestionText'  => $q['QuestionText'],
                'QuestionType'  => $q['QuestionType'],
                'Options'       => isset($q['Options'])
                                   ? json_encode($q['Options'])
                                   : null,
                'CorrectAnswer' => $q['CorrectAnswer'] ?? null,
                'Marks'         => $q['Marks'],
            ]);
        }

        // Step 4 — Notify all target students
        $students = User::where('TargetCategory', $request->TargetCategory)
                        ->where('Role', 'Student')
                        ->get();

        foreach ($students as $student) {
            Notification::create([
                'UserID'  => $student->UserID,
                'Message' => 'New quiz scheduled: ' . $request->Title,
                'Status'  => 'Unread',
                'Type'    => 'Quiz Announcement',
            ]);
        }

        // Step 5 — Return success
        return response()->json([
            'message'           => 'Quiz scheduled successfully!',
            'QuizID'            => $quiz->QuizID,
            'students_notified' => $students->count(),
        ], 201);
    }

    // ─── Load quiz list view ─────────────────────────────────────
    public function index()
    {
        $quizzes = Quiz::all();
        return view('quizzes.index', compact('quizzes'));
    }

    // ─── Load quiz taking view ───────────────────────────────────
    public function take($id)
    {
        $quiz      = Quiz::findOrFail($id);
        $questions = $quiz->questions;
        return view('quizzes.take', compact('quiz', 'questions'));
    }
}