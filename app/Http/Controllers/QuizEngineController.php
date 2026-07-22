<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use App\Models\QuizResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Dompdf\Dompdf;

class QuizEngineController extends Controller
{
    // ─── STEP 1: Student joins a quiz ───────────────────────────
    public function join(Request $request)
    {
        $request->validate(['QuizID' => 'required|integer']);

        $user   = auth()->user();
        $quizID = $request->QuizID;
        $quiz   = Quiz::find($quizID);

        if (!$quiz) {
            return response()->json(['error' => 'Quiz not found.'], 404);
        }

        // Calculate how many minutes have passed since quiz started
        $startTime   = Carbon::parse($quiz->StartTime);
        $now         = Carbon::now();
        $elapsed     = $startTime->diffInSeconds($now, false); // negative = not started yet

        // Quiz hasn't started yet
        if ($elapsed < 0) {
            return response()->json(['error' => 'Quiz has not started yet.'], 403);
        }

        $elapsedMinutes  = $elapsed / 60;
        $durationSeconds = $quiz->Duration * 60;

        // Quiz is already over
        if ($elapsedMinutes >= $quiz->Duration) {
            return response()->json(['error' => 'Quiz has already closed.'], 403);
        }

        // Student already submitted
        $existing = QuizResult::where('QuizID', $quizID)
                               ->where('UserID', $user->UserID)
                               ->first();
        if ($existing) {
            return response()->json(['error' => 'You have already submitted this quiz.'], 403);
        }

        // Remaining time for this student (late joiners get less time)
        $allocatedSeconds = $durationSeconds - $elapsed;

        // Fetch questions (never send CorrectAnswer to the student)
        $questions = Question::where('QuizID', $quizID)
            ->get()
            ->map(function ($q) {
                return [
                    'QuestionID'   => $q->QuestionID,
                    'QuestionText' => $q->QuestionText,
                    'QuestionType' => $q->QuestionType,
                    'Options'      => $q->Options,
                    'Marks'        => $q->Marks,
                ];
            });

        return response()->json([
            'QuizID'           => $quiz->QuizID,
            'Title'            => $quiz->Title,
            'AllocatedSeconds' => $allocatedSeconds, // Use this for the countdown timer
            'Questions'        => $questions,
        ]);
    }

    // ─── STEP 2: Grade answers ──────────────────────────────────
    private function gradeAnswers(array $answers): array
    {
        $score   = 0;
        $graded  = [];

        foreach ($answers as $a) {
            $question  = Question::find($a['QuestionID']);
            if (!$question) continue;

            $isCorrect = false;

            if ($question->QuestionType === 'MCQ') {
                // Case-insensitive comparison
                $isCorrect = strtolower(trim($a['ResponseText']))
                          === strtolower(trim($question->CorrectAnswer));
            }

            if ($isCorrect) {
                $score += $question->Marks;
            }

            $graded[] = [
                'QuestionID'   => $a['QuestionID'],
                'ResponseText' => $a['ResponseText'],
                'IsCorrect'    => $isCorrect,
                // Starting point for the lecturer's review, not the final
                // word - Open-ended questions always start at 0 here since
                // they're never auto-graded to "correct" above.
                'MarksAwarded' => $isCorrect ? $question->Marks : 0,
            ];
        }

        return ['score' => $score, 'graded' => $graded];
    }

    // ─── STEP 3: Save result and answers to database ────────────
    private function saveResult(int $quizID, int $userID, float $score,
                                array $graded, bool $isAutoSubmit): QuizResult
    {
        $result = QuizResult::create([
            'QuizID'         => $quizID,
            'UserID'         => $userID,
            'Score'          => $score,
            'SubmissionTime' => Carbon::now(),
            'IsAutoSubmit'   => $isAutoSubmit,
        ]);

        foreach ($graded as $g) {
            Answer::create([
                'QuestionID'   => $g['QuestionID'],
                'ResultID'     => $result->ResultID,
                'UserID'       => $userID,
                'ResponseText' => $g['ResponseText'],
                'IsCorrect'    => $g['IsCorrect'],
                'MarksAwarded' => $g['MarksAwarded'],
            ]);
        }

        return $result;
    }

    // ─── STEP 4: Manual submit (student clicks Submit) ──────────
    public function submit(Request $request)
    {
        $request->validate([
            'QuizID'  => 'required|integer',
            'Answers' => 'required|array',
            'Answers.*.QuestionID'   => 'required|integer',
            'Answers.*.ResponseText' => 'required|string',
        ]);

        $user   = auth()->user();
        $quizID = $request->QuizID;
        $quiz   = Quiz::find($quizID);

        if (!$quiz) {
            return response()->json(['error' => 'Quiz not found.'], 404);
        }

        // Prevent duplicate submissions
        $existing = QuizResult::where('QuizID', $quizID)
                               ->where('UserID', $user->UserID)
                               ->first();
        if ($existing) {
            return response()->json(['error' => 'Already submitted.'], 403);
        }

        // Check quiz is not over (server validates, not client)
        $elapsed = Carbon::parse($quiz->StartTime)->diffInMinutes(Carbon::now(), false);
        if ($elapsed >= $quiz->Duration) {
            // Time is up — treat as auto-submit
            $result = $this->gradeAndSave($quizID, $user->UserID, $request->Answers, true);
            return response()->json([
                'message'      => 'Time expired. Answers auto-submitted.',
                'Score'        => $result->Score,
                'IsAutoSubmit' => true,
            ]);
        }

        $grading = $this->gradeAnswers($request->Answers);
        $result  = $this->saveResult($quizID, $user->UserID,
                                     $grading['score'], $grading['graded'], false);

        return response()->json([
            'message'      => 'Quiz submitted successfully.',
            'Score'        => $result->Score,
            'IsAutoSubmit' => false,
        ]);
    }

    // ─── STEP 5: Auto-submit (timer hit zero on client) ─────────
    public function autoSubmit(Request $request)
    {
        $request->validate([
            'QuizID'  => 'required|integer',
            'Answers' => 'nullable|array',
            'Answers.*.QuestionID'   => 'sometimes|integer',
            'Answers.*.ResponseText' => 'sometimes|string',
        ]);

        $user    = auth()->user();
        $quizID  = $request->QuizID;
        $quiz    = Quiz::find($quizID);

        if (!$quiz) {
            return response()->json(['error' => 'Quiz not found.'], 404);
        }

        // Prevent duplicate submissions
        $existing = QuizResult::where('QuizID', $quizID)
                               ->where('UserID', $user->UserID)
                               ->first();
        if ($existing) {
            return response()->json(['message' => 'Already submitted.'], 200);
        }

        $answers  = $request->Answers ?? [];
        $grading  = $this->gradeAnswers($answers);
        $result   = $this->saveResult($quizID, $user->UserID,
                                      $grading['score'], $grading['graded'], true);

        return response()->json([
            'message'      => 'Time expired. Answers saved automatically.',
            'Score'        => $result->Score,
            'IsAutoSubmit' => true,
        ]);
    }

    // ─── Helper used when manual submit arrives after time ──────
    private function gradeAndSave(int $quizID, int $userID,
                                  array $answers, bool $isAutoSubmit): QuizResult
    {
        $grading = $this->gradeAnswers($answers);
        return $this->saveResult($quizID, $userID,
                                 $grading['score'], $grading['graded'], $isAutoSubmit);
    }
    // ─── Results / Performance Report ───────────────────────────
public function results($quizID)
{
    $quiz = Quiz::find($quizID);

    if (!$quiz) {
        return response()->json(['error' => 'Quiz not found.'], 404);
    }

    $results = QuizResult::where('QuizID', $quizID)
        ->join('User', 'QuizResult.UserID', '=', 'User.UserID')
        ->select(
            'QuizResult.ResultID',
            'QuizResult.UserID',
            'User.UserName as StudentName',
            'QuizResult.Score',
            'QuizResult.SubmissionTime',
            'QuizResult.IsAutoSubmit'
        )
        ->orderBy('QuizResult.Score', 'desc')
        ->get();

    $totalMarks = Question::where('QuizID', $quizID)->sum('Marks');

    return response()->json([
        'QuizID'     => $quiz->QuizID,
        'Title'      => $quiz->Title,
        'TotalMarks' => $totalMarks,
        'Results'    => $results,
    ]);
}

// ─── Lecturer review: see one student's question-by-question answers
// and award marks per question (auto-grading never gives credit for
// Open-ended questions - see gradeAnswers() - so this is the only way
// those ever get real marks) ─────────────────────────────────────────
public function reviewSubmission($quizID, $resultID)
{
    $quiz = Quiz::findOrFail($quizID);
    $result = QuizResult::where('QuizID', $quizID)->findOrFail($resultID);
    $student = \App\Models\User::find($result->UserID);

    $questions = Question::where('QuizID', $quizID)->get();
    $answers = Answer::where('ResultID', $resultID)->get()->keyBy('QuestionID');

    $totalMarks = $questions->sum('Marks');

    return view('quizzes.review-submission', compact(
        'quiz', 'result', 'student', 'questions', 'answers', 'totalMarks'
    ));
}

// Awards marks for one question's answer and recalculates the result's
// overall Score as the sum of every answer's MarksAwarded - "update
// immediately" per the request that prompted this, so the lecturer sees
// the new total the moment they change one question, not after a
// separate save step.
public function updateAnswerMarks(Request $request, $resultID, $answerID)
{
    $answer = Answer::where('ResultID', $resultID)->findOrFail($answerID);
    $question = Question::findOrFail($answer->QuestionID);
    Quiz::where('QuizID', $question->QuizID)
        ->where('LecturerID', auth()->id())
        ->firstOrFail();

    $validated = $request->validate([
        'marks' => ['required', 'numeric', 'min:0', 'max:' . $question->Marks],
    ]);

    $answer->update(['MarksAwarded' => $validated['marks']]);

    $newTotal = Answer::where('ResultID', $resultID)->sum('MarksAwarded');
    QuizResult::where('ResultID', $resultID)->update(['Score' => $newTotal]);

    return response()->json([
        'success' => true,
        'marks_awarded' => (float) $validated['marks'],
        'new_total_score' => (float) $newTotal,
    ]);
}

// Lets the lecturer set/fix a question's maximum marks directly from the
// review screen (e.g. a question that was scheduled with none set at
// all) instead of having to go rebuild the quiz. Any already-awarded
// marks that now exceed the new ceiling are clamped down, and every
// submission's Score that touches this question is recalculated so
// nothing drifts out of sync.
public function updateQuestionMarks(Request $request, $resultID, $questionID)
{
    $question = Question::findOrFail($questionID);
    Quiz::where('QuizID', $question->QuizID)
        ->where('LecturerID', auth()->id())
        ->firstOrFail();

    $validated = $request->validate([
        'marks' => ['required', 'numeric', 'min:0'],
    ]);

    $question->update(['Marks' => $validated['marks']]);

    Answer::where('QuestionID', $questionID)
        ->where('MarksAwarded', '>', $validated['marks'])
        ->update(['MarksAwarded' => $validated['marks']]);

    $affectedResultIds = Answer::where('QuestionID', $questionID)->pluck('ResultID')->unique();
    foreach ($affectedResultIds as $rid) {
        $sum = Answer::where('ResultID', $rid)->sum('MarksAwarded');
        QuizResult::where('ResultID', $rid)->update(['Score' => $sum]);
    }

    $currentAnswer = Answer::where('ResultID', $resultID)->where('QuestionID', $questionID)->first();

    return response()->json([
        'success' => true,
        'question_marks' => (float) $validated['marks'],
        'marks_awarded' => (float) ($currentAnswer->MarksAwarded ?? 0),
        'new_total_score' => (float) Answer::where('ResultID', $resultID)->sum('MarksAwarded'),
        'new_quiz_total_marks' => (float) Question::where('QuizID', $question->QuizID)->sum('Marks'),
    ]);
}

// Same shape as results() above, rendered to PDF via Dompdf instead of
// JSON - the "Export PDF" button on quizzes/results.blade.php was a
// disabled stub until now (see the note there about no endpoint existing).
public function exportResultsPdf($quizID)
{
    $quiz = Quiz::findOrFail($quizID);

    $results = QuizResult::where('QuizID', $quizID)
        ->join('User', 'QuizResult.UserID', '=', 'User.UserID')
        ->select(
            'QuizResult.ResultID',
            'QuizResult.UserID',
            'User.UserName as StudentName',
            'QuizResult.Score',
            'QuizResult.SubmissionTime',
            'QuizResult.IsAutoSubmit'
        )
        ->orderBy('QuizResult.Score', 'desc')
        ->get();

    $totalMarks = Question::where('QuizID', $quizID)->sum('Marks');
    $attempted = $results->count();
    $average = $attempted > 0 ? round($results->avg('Score'), 2) : 0;
    $highest = $attempted > 0 ? round($results->max('Score'), 2) : 0;
    $autoCount = $results->where('IsAutoSubmit', 1)->count();

    $html = view('quizzes.results_export_pdf', [
        'quiz' => $quiz,
        'results' => $results,
        'totalMarks' => $totalMarks,
        'attempted' => $attempted,
        'average' => $average,
        'highest' => $highest,
        'autoCount' => $autoCount,
    ])->render();

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = Str::slug($quiz->Title ?: 'quiz') . '-results.pdf';

    return response($dompdf->output(), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
    ]);
}

public function latestResults()
{
    $lecturerId = Auth::id();

    $latestQuiz = Quiz::where('LecturerID', $lecturerId)
        ->where('Status', 'scheduled')
        ->orderByDesc('StartTime')
        ->first();

    if (!$latestQuiz) {
        return view('quizzes.results', ['quizID' => null, 'noQuiz' => true]);
    }

    return redirect()->route('quiz.results', $latestQuiz->QuizID);
}

public function myReview($quizID)
{
    $userId = auth()->id();
    $quiz = Quiz::findOrFail($quizID);

    $result = QuizResult::where('QuizID', $quizID)
        ->where('UserID', $userId)
        ->latest('SubmissionTime')
        ->firstOrFail();

    $questions = Question::where('QuizID', $quizID)->get();

    $myAnswers = DB::table('answer')
        ->where('ResultID', $result->ResultID)
        ->where('UserID', $userId)
        ->get()
        ->keyBy('QuestionID');

    return view('quizzes.my-review', compact('quiz', 'result', 'questions', 'myAnswers'));
}

// JSON twin of myReview() above, for the desktop client - same data,
// no Blade view. Reveals CorrectAnswer only for questions the student
// got wrong or left unanswered, mirroring my-review.blade.php exactly.
public function myReviewApi($quizID)
{
    $userId = auth()->id();
    $quiz = Quiz::findOrFail($quizID);

    $result = QuizResult::where('QuizID', $quizID)
        ->where('UserID', $userId)
        ->latest('SubmissionTime')
        ->firstOrFail();

    $questions = Question::where('QuizID', $quizID)->get();

    $myAnswers = DB::table('answer')
        ->where('ResultID', $result->ResultID)
        ->where('UserID', $userId)
        ->get()
        ->keyBy('QuestionID');

    return response()->json([
        'quiz_id' => $quiz->QuizID,
        'title' => $quiz->Title,
        'score' => $result->Score,
        'submitted_at' => optional($result->SubmissionTime)->diffForHumans(),
        'questions' => $questions->values()->map(function ($q) use ($myAnswers) {
            $mine = $myAnswers->get($q->QuestionID);
            $isCorrect = $mine && $mine->IsCorrect;
            return [
                'question_id' => $q->QuestionID,
                'question_text' => $q->QuestionText,
                'marks' => $q->Marks,
                'your_answer' => $mine->ResponseText ?? null,
                'is_correct' => (bool) $isCorrect,
                'correct_answer' => $isCorrect ? null : $q->CorrectAnswer,
            ];
        }),
    ]);
}

// ─── Lecturer: view one student's submission for grading ────
public function gradeView($resultId)
{
    $result = QuizResult::with('quiz')->findOrFail($resultId);

    abort_unless($result->quiz->LecturerID === auth()->id(), 403);

    $student = \App\Models\User::find($result->UserID);
    $questions = Question::where('QuizID', $result->QuizID)->orderBy('QuestionID')->get();
    $answers = Answer::where('ResultID', $resultId)->get()->keyBy('QuestionID');

    return view('quizzes.grade', compact('result', 'student', 'questions', 'answers'));
}

// ─── Lecturer: save marks for this student's Open-ended answers ─
// MCQ answers are already graded automatically at submission time
// (gradeAnswers() above) - this only ever touches Open-type answers,
// which are otherwise stuck at 0 marks forever with no other path to
// award them.
public function gradeSubmit(Request $request, $resultId)
{
    $result = QuizResult::with('quiz')->findOrFail($resultId);

    abort_unless($result->quiz->LecturerID === auth()->id(), 403);

    $request->validate([
        'marks'   => 'array',
        'marks.*' => 'nullable|numeric|min:0',
    ]);

    $marks   = $request->input('marks', []);
    $answers = Answer::where('ResultID', $resultId)->with('question')->get();

    foreach ($answers as $answer) {
        if ($answer->question->QuestionType !== 'Open' || !array_key_exists($answer->AnswerID, $marks)) {
            continue;
        }

        $awarded = $marks[$answer->AnswerID];
        $awarded = ($awarded === null || $awarded === '')
            ? null
            : min((float) $awarded, (float) $answer->question->Marks);

        $answer->update([
            'MarksAwarded' => $awarded,
            'IsCorrect'    => $awarded !== null && $awarded > 0,
        ]);
    }

    // Recompute the total: MCQ contributes full marks only when IsCorrect
    // (set automatically at submission), Open contributes whatever's been
    // manually awarded so far (0 while still ungraded) - so Score always
    // reflects exactly what's been marked instead of permanently excluding
    // Open questions like it did before any manual grading existed.
    $score = Answer::where('ResultID', $resultId)->with('question')->get()
        ->sum(fn ($a) => $a->question->QuestionType === 'MCQ'
            ? ($a->IsCorrect ? $a->question->Marks : 0)
            : ($a->MarksAwarded ?? 0));

    $result->update(['Score' => $score]);

    return redirect()->route('quiz.grade', $resultId)->with('success', 'Marks saved.');
}

public function activeNow(Request $request)
{
    $user = auth()->user();

    // Quizzes are something a student takes, not something a lecturer or
    // admin gets pulled into - without this, the "Quiz Starting Now!" popup
    // (and its quiz-join flow) fired for every role, since the query below
    // never filtered by who's asking.
    if ($user->Role !== 'Student') {
        return response()->json(['quiz' => null]);
    }

    $now  = now();

    $quiz = \App\Models\Quiz::where('StartTime', '<=', $now)
        ->whereRaw("DATE_ADD(StartTime, INTERVAL Duration MINUTE) >= ?", [$now])
        ->whereNotExists(function($q) use ($user) {
            $q->select(\DB::raw(1))
              ->from('QuizResult')
              ->whereColumn('QuizResult.QuizID', 'Quiz.QuizID')
              ->where('QuizResult.UserID', $user->UserID);
        })
        ->first();

    return response()->json(['quiz' => $quiz]);
}
}