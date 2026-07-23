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
        // ParticipationService::recalculate() already computes an accepted-
        // answer count to factor into ParticipationScore, but discarded it
        // afterward instead of persisting it - so a student's Marks screen
        // had no way to show how many of their answers were marked correct,
        // only the blended total score.
        Schema::table('Participation', function (Blueprint $table) {
            $table->integer('AcceptedCount')->default(0)->after('ReplyCount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Participation', function (Blueprint $table) {
            $table->dropColumn('AcceptedCount');
        });
    }
};
