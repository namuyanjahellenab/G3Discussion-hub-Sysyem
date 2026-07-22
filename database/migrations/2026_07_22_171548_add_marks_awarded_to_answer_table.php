<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('Answer', function (Blueprint $table) {
            $table->decimal('MarksAwarded', 6, 2)->nullable()->after('IsCorrect');
        });

        // Backfill existing answers to match the auto-grader's own logic
        // (gradeAnswers() in QuizEngineController: full marks if IsCorrect,
        // zero otherwise) - open-ended questions were never auto-graded to
        // "correct", so this preserves their current (zero) marks until a
        // lecturer reviews and awards real credit.
        DB::statement(
            'UPDATE `Answer` a '
            . 'JOIN `Question` q ON q.QuestionID = a.QuestionID '
            . 'SET a.MarksAwarded = IF(a.IsCorrect = 1, q.Marks, 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Answer', function (Blueprint $table) {
            $table->dropColumn('MarksAwarded');
        });
    }
};
