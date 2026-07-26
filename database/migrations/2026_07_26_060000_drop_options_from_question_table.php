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
        // Fully superseded by question_options (see 2026_07_26_040000 and
        // 2026_07_26_050000's backfill) - every read/write path has been
        // moved over and verified end-to-end (scheduling, editing a draft,
        // taking a quiz, grading). Keeping both would just recreate the
        // exact redundancy this normalization was for.
        Schema::table('Question', function (Blueprint $table) {
            $table->dropColumn('Options');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Question', function (Blueprint $table) {
            $table->text('Options')->nullable()->after('QuestionType');
        });
    }
};
