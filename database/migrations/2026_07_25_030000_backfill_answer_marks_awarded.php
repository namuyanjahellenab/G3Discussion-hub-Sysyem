<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The backfill intended by 2026_07_22_171548_add_marks_awarded_to_answer_table.php
        // never actually ran (see that file's comment) - any Answer row that
        // existed before MarksAwarded was added is still sitting at NULL.
        // Matches the auto-grader's own logic (QuizEngineController::
        // gradeAnswers(): full marks if IsCorrect, zero otherwise). Guarded
        // by "WHERE MarksAwarded IS NULL" (not hasColumn) so this only ever
        // touches rows that were never graded, not ones a lecturer has since
        // manually awarded marks to.
        DB::statement(
            'UPDATE `Answer` a '
            . 'JOIN `Question` q ON q.QuestionID = a.QuestionID '
            . 'SET a.MarksAwarded = IF(a.IsCorrect = 1, q.Marks, 0) '
            . 'WHERE a.MarksAwarded IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible - there's no way to tell which NULLs were
        // legitimately backfilled by this migration versus ones that would
        // have been NULL anyway.
    }
};
