<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use App\Models\QuizResult;
use Carbon\Carbon;

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
public function activeNow(Request $request)
{
    $user = auth()->user();
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