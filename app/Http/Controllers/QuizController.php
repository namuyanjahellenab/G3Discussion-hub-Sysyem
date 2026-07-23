<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

class QuizController extends Controller
{
    // Loads the quiz scheduling screen — optionally pre-filled from a saved draft
public function create(Request $request)
{
    $groups = \App\Models\Group::orderBy('GroupName')->get();

    $draft = null;
    if ($request->filled('draft')) {
        $draft = Quiz::where('QuizID', $request->input('draft'))
            ->where('LecturerID', auth()->id())
            ->where('Status', 'draft')
            ->with('questions')
            ->first();
    }

    return view('quizzes.schedule', compact('groups', 'draft'));
}

    // Loads an already-scheduled quiz into the same builder screen for editing
    public function edit($id)
    {
        $groups = \App\Models\Group::orderBy('GroupName')->get();

        $draft = Quiz::where('QuizID', $id)
            ->where('LecturerID', auth()->id())
            ->with('questions')
            ->firstOrFail();

        return view('quizzes.schedule', compact('groups', 'draft'));
    }

    // ─── WEEK 1: Schedule a quiz ────────────────────────────────
    public function scheduleAssessment(Request $request)
    {
        // Step 1 — Validate all incoming data
        $request->validate([
    'QuizID'         => 'nullable|integer|exists:Quiz,QuizID',
    'Title'          => 'required|string|max:255',
    'StartTime'      => 'required|date',
    'Duration'       => 'required|integer|min:1',
    'TargetCategory' => 'nullable|string|max:100',
    'GroupID'        => 'required|integer|exists:Group,GroupID',
    'Questions'      => 'required|array|min:1',
            'Questions.*.QuestionText'  => 'required|string',
            'Questions.*.QuestionType' => 'required|in:MCQ,Open',
            'Questions.*.Options'      => 'nullable|array',
            'Questions.*.CorrectAnswer'=> 'nullable|string',
            'Questions.*.Marks'        => 'required|numeric|min:0',
        ]);

        // Step 2 — Save the quiz — finalize an existing draft in place if one
        // was passed, otherwise create a brand new quiz.
        $quiz = null;
        if ($request->filled('QuizID')) {
            $quiz = Quiz::where('QuizID', $request->QuizID)
                ->where('LecturerID', auth()->id())
                ->first();
        }
        $isEditOfLiveQuiz = $quiz && $quiz->Status === 'scheduled';

        $attributes = [
            'LecturerID'     => auth()->user()->UserID,
            'GroupID'        => $request->GroupID,
            'Title'          => $request->Title,
            'StartTime'      => Carbon::parse($request->StartTime, 'Africa/Kampala')->setTimezone('UTC'),
            'Duration'       => $request->Duration,
            'TargetCategory' => $request->TargetCategory,
            'Status'         => 'scheduled',
        ];

        if ($quiz) {
            $quiz->update($attributes);
            $quiz->questions()->delete();
        } else {
            $quiz = Quiz::create($attributes);
        }

        // Step 3 — Save the questions
        foreach ($request->Questions as $q) {
            Question::create([
                'QuizID'        => $quiz->QuizID,
                'QuestionText'  => $q['QuestionText'],
                'QuestionType'  => $q['QuestionType'],
               'Options'       => $q['Options'] ?? null,
                'CorrectAnswer' => $q['CorrectAnswer'] ?? null,
                'Marks'         => $q['Marks'],
            ]);
        }

        // Step 4 — Notify all active students in the target group
        // GroupStudent.Status is stored lowercase ('active') everywhere else
        // in the app (GroupChatService, DashboardController, Group::
        // activeMembers()) - this only matched real rows before because
        // MySQL/MariaDB's default collation happens to compare case-
        // insensitively, which made the actual audience size for a published
        // quiz depend on a DB collation quirk instead of an explicit, correct
        // comparison.
$students = User::whereHas('groupMemberships', function ($q) use ($request) {
                    $q->where('GroupID', $request->GroupID)
                      ->where('Status', 'active');
                })
                ->where('Role', 'Student')
                ->get();
        $notifMessage = $isEditOfLiveQuiz
            ? 'Quiz updated: ' . $request->Title
            : 'New quiz scheduled: ' . $request->Title;
        foreach ($students as $student) {
            Notification::create([
                'UserID'  => $student->UserID,
                'Message' => $notifMessage,
                'Status'  => false,
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

    // ─── Save (or update) a draft — every field is optional ─────
    public function saveDraft(Request $request)
    {
        $request->validate([
            'QuizID'         => 'nullable|integer|exists:Quiz,QuizID',
            'Title'          => 'nullable|string|max:255',
            'StartTime'      => 'nullable|date',
            'Duration'       => 'nullable|integer|min:1',
            'TargetCategory' => 'nullable|string|max:100',
            'GroupID'        => 'nullable|integer|exists:Group,GroupID',
            'Questions'      => 'nullable|array',
            'Questions.*.QuestionText'  => 'nullable|string',
            'Questions.*.QuestionType'  => 'nullable|in:MCQ,Open',
            'Questions.*.Options'       => 'nullable|array',
            'Questions.*.CorrectAnswer' => 'nullable|string',
            'Questions.*.Marks'         => 'nullable|numeric|min:0',
        ]);

        $quiz = null;
        if ($request->filled('QuizID')) {
            $quiz = Quiz::where('QuizID', $request->QuizID)
                ->where('LecturerID', auth()->id())
                ->first();
        }

        // Quiz has $timestamps = false, so CreatedAt/UpdatedAt are otherwise
        // left entirely to the DB's own useCurrent()/useCurrentOnUpdate()
        // defaults - which stamp the DB server's local system clock (MariaDB
        // time_zone=SYSTEM here), not the app's actual UTC time. That mismatch
        // is what made "Last saved {{ $draft->UpdatedAt->diffForHumans() }}"
        // on the drafts screen show a nonsensical time. Setting it explicitly
        // with the app's own now() keeps it consistent with whatever
        // diffForHumans() compares it against later.
        $attributes = [
            'LecturerID'     => auth()->id(),
            'GroupID'        => $request->input('GroupID'),
            'Title'          => $request->input('Title') ?: 'Untitled draft',
            'StartTime'      => $request->filled('StartTime')
                ? Carbon::parse($request->StartTime, 'Africa/Kampala')->setTimezone('UTC')
                : null,
            'Duration'       => $request->input('Duration'),
            'TargetCategory' => $request->input('TargetCategory'),
            'Status'         => 'draft',
            'UpdatedAt'      => now(),
        ];

        if ($quiz) {
            $quiz->update($attributes);
            $quiz->questions()->delete();
        } else {
            $quiz = Quiz::create($attributes + ['CreatedAt' => now()]);
        }

        foreach ($request->input('Questions', []) as $q) {
            if (empty($q['QuestionText'])) {
                continue;
            }

            Question::create([
                'QuizID'        => $quiz->QuizID,
                'QuestionText'  => $q['QuestionText'],
                'QuestionType'  => $q['QuestionType'] ?? 'MCQ',
                'Options'       => $q['Options'] ?? null,
                'CorrectAnswer' => $q['CorrectAnswer'] ?? null,
                'Marks'         => $q['Marks'] ?? 0,
            ]);
        }

        return response()->json([
            'message' => 'Draft saved.',
            'QuizID'  => $quiz->QuizID,
        ], 200);
    }

    // ─── List this lecturer's saved drafts ──────────────────────
    public function drafts()
    {
        $drafts = Quiz::where('LecturerID', auth()->id())
            ->where('Status', 'draft')
            ->with('group')
            ->withCount('questions')
            ->latest('UpdatedAt')
            ->get();

        return view('quizzes.drafts', compact('drafts'));
    }

    // ─── Delete a saved draft ────────────────────────────────────
    public function deleteDraft($id)
    {
        $quiz = Quiz::where('QuizID', $id)
            ->where('LecturerID', auth()->id())
            ->where('Status', 'draft')
            ->firstOrFail();

        $quiz->questions()->delete();
        $quiz->delete();

        return redirect()->route('quiz.drafts')->with('status', 'Draft deleted.');
    }

    // ─── Delete a scheduled/live quiz and everything tied to it ─
    public function destroy(Quiz $quiz)
    {
        abort_unless($quiz->LecturerID === auth()->id(), 403);

        // Explicit for clarity even though Question.QuizID and
        // QuizResult.QuizID both already cascade on delete at the DB level
        // (and Answer.ResultID cascades from QuizResult in turn) - deleting
        // the quiz removes every student's result and answers for it, so
        // their marks disappear wherever they're shown (Marks pages,
        // Statistics averages, Top Students) since all of those read
        // QuizResult live rather than a cached copy.
        $quiz->questions()->delete();
        $quiz->results()->delete();
        $quiz->delete();

        return back()->with('success', 'Quiz and all its results deleted.');
    }

}