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
        // TopicSeeder auto-creates one placeholder Topic per group (e.g.
        // "Algorithms", "Databases"), CreatedBy a seeded "System" user, so
        // every group would have at least one entry to show. They show up
        // in "Recent Discussions" on the lecturer/student dashboards as
        // "Started by System" with 0 replies and can't really be answered -
        // they were never meant to be real discussions. Removes them, but
        // only where nobody has actually posted under them (no Post rows),
        // so a topic that happens to share a seeded title/creator but has
        // real engagement is left untouched.
        $systemUserId = DB::table('User')->where('Email', 'system@example.com')->value('UserID');

        if (!$systemUserId) {
            return;
        }

        $topicIds = DB::table('Topic')->where('CreatedBy', $systemUserId)->pluck('TopicID');

        foreach ($topicIds as $topicId) {
            $hasPosts = DB::table('Post')->where('TopicID', $topicId)->exists();

            if (!$hasPosts) {
                DB::table('Topic')->where('TopicID', $topicId)->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible - these were unused placeholder topics with no
        // real content; there's nothing correct to restore.
    }
};
