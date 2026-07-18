<?php

namespace App\Services;

use App\Models\QuizResult;
use Illuminate\Support\Collection;

// Powers the "Marks" page on both web and desktop: one row per group the
// student belongs to, each with curved participation (ParticipationService)
// and that group's own quiz results - replacing the old single blended
// average (which hid a student's per-group standing) and the placeholder
// coursework/cats/exams/gpa figures that were never backed by real data.
class MarksService
{
    public function __construct(private ParticipationService $participationService)
    {
    }

    public function marksBreakdownForUser(int $userId): Collection
    {
        $participationByGroup = $this->participationService
            ->curvedScoresForUser($userId)
            ->keyBy('group_id');

        $quizResults = QuizResult::where('UserID', $userId)
            ->with('quiz.group')
            ->orderByDesc('SubmissionTime')
            ->get()
            ->filter(fn ($r) => $r->quiz?->group !== null)
            ->groupBy(fn ($r) => $r->quiz->group->GroupID);

        // A group the student has quiz results in but no (or a fresh,
        // zero-score) participation row still needs to show up, and vice
        // versa - union both sources rather than driving off just one.
        $groupIds = $participationByGroup->keys()->merge($quizResults->keys())->unique();

        return $groupIds->map(function ($groupId) use ($participationByGroup, $quizResults) {
            $participation = $participationByGroup->get($groupId);
            $results = $quizResults->get($groupId, collect());
            $quizAverage = $results->isNotEmpty() ? round($results->avg('Score'), 1) : null;

            return [
                'group_id' => $groupId,
                'group_name' => $participation['group_name']
                    ?? $results->first()?->quiz?->group?->GroupName
                    ?? 'Group',
                'participation' => $participation['participation'] ?? 0.0,
                'post_count' => $participation['post_count'] ?? 0,
                'reply_count' => $participation['reply_count'] ?? 0,
                'quiz_average' => $quizAverage,
                'quizzes_taken' => $results->count(),
                'quizzes' => $results->map(fn ($r) => [
                    'quiz_id' => $r->QuizID,
                    'title' => $r->quiz->Title ?? 'Deleted quiz',
                    'score' => $r->Score,
                    'submitted_at' => optional($r->SubmissionTime)->format('Y-m-d H:i'),
                    'submitted_ago' => optional($r->SubmissionTime)->diffForHumans(),
                    'auto_submitted' => (bool) $r->IsAutoSubmit,
                ])->values(),
            ];
        })->sortBy('group_name')->values();
    }
}
