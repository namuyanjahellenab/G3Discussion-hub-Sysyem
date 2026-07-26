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
        // Written on every quiz (QuizController::scheduleAssessment()/
        // saveDraft()) but never read anywhere - no filter, no display, and
        // no Blade form field ever populates it, so it was always null in
        // practice. Confirmed dead by the user before dropping.
        Schema::table('Quiz', function (Blueprint $table) {
            $table->dropColumn('TargetCategory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Quiz', function (Blueprint $table) {
            $table->string('TargetCategory', 100)->nullable()->after('Duration');
        });
    }
};
