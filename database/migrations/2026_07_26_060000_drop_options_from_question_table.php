<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Deliberately deferred: question_options (2026_07_26_040000 +
        // 2026_07_26_050000's backfill) is populated and every read path
        // already goes through Question::getOptionsAttribute() instead of
        // this raw column, so it's already inert - safe to leave in place
        // a while longer as a free safety net. Dropping it is irreversible
        // on a production database with real quiz data, so that only
        // happens once the backfill has been manually spot-checked against
        // the live data (not just the fresh/seeded test data this was
        // verified against). Do that check, then actually drop the column
        // in a follow-up migration.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op - see up(). The column was never dropped, so there's
        // nothing to restore.
    }
};
