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
        // The existing Options column is JSON-encoded (via the model's
        // 'array' cast) - decode it directly here rather than going through
        // Eloquent, since this migration runs before the model is updated
        // to stop expecting that column.
        $questions = DB::table('Question')->whereNotNull('Options')->get(['QuestionID', 'Options']);

        foreach ($questions as $question) {
            $options = json_decode($question->Options, true);

            if (!is_array($options)) {
                continue;
            }

            foreach (array_values($options) as $position => $optionText) {
                if ($optionText === null || $optionText === '') {
                    continue;
                }

                DB::table('question_options')->insert([
                    'QuestionID' => $question->QuestionID,
                    'OptionText' => $optionText,
                    'Position' => $position,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op - the source Options column is untouched by this migration
        // (only read from), so there's nothing to restore. Rolling back
        // 2026_07_26_040000 (dropping the table) removes the backfilled
        // rows anyway.
    }
};
