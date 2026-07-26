<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Superseded by 2026_07_22_080000_add_marks_awarded_to_answer_table.php,
        // which always runs first (earlier timestamp) and adds the same
        // column at decimal(5,2) - matching Question.Marks/QuizResult.Score,
        // which are also (5,2). Because of that, this migration's own
        // hasColumn() guard always found the column already present and
        // returned early, so its decimal(6,2) definition and its backfill
        // UPDATE never actually ran on any environment. Left as a
        // permanent no-op rather than deleted, since this migration may
        // already be recorded as run in the `migrations` table on some
        // environments. The backfill this was meant to do is now handled by
        // 2026_07_25_030000_backfill_answer_marks_awarded.php instead.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op - see up().
    }
};
