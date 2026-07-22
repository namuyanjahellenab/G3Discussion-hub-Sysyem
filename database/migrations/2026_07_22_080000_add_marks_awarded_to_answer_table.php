<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Open-ended (non-MCQ) answers are never auto-graded - gradeAnswers()
        // in QuizEngineController leaves IsCorrect false and awards 0 marks
        // for them at submission time. This column lets a lecturer record
        // the marks they manually award per answer afterward; nullable so an
        // ungraded Open answer is distinguishable from one deliberately
        // awarded 0.
        //
        // A teammate's migration (2026_07_22_171548) adds this same column -
        // whichever of the two runs second on a given machine must be a
        // no-op instead of failing on a duplicate column.
        if (Schema::hasColumn('Answer', 'MarksAwarded')) {
            return;
        }

        Schema::table('Answer', function (Blueprint $table) {
            $table->decimal('MarksAwarded', 5, 2)->nullable()->after('IsCorrect');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('Answer', 'MarksAwarded')) {
            return;
        }

        Schema::table('Answer', function (Blueprint $table) {
            $table->dropColumn('MarksAwarded');
        });
    }
};
