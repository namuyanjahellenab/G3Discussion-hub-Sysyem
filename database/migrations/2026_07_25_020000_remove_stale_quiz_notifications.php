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
        // Before QuizController::scheduleAssessment() tagged notifications
        // with GroupID (and before GroupController::leave() cleaned them up
        // on exit), a "New quiz scheduled"/"Quiz updated" notification had
        // no way to be removed once its recipient was no longer in that
        // quiz's group - it just sat there forever. Clicking one led
        // straight to "Cannot Load Quiz - You are not a member of this
        // quiz's group" (QuizEngineController::isGroupMember()). This finds
        // every such notification still sitting in an inbox it doesn't
        // belong in and removes it.
        $notifications = DB::table('Notification')->where('Type', 'Quiz Announcement')->get();

        foreach ($notifications as $notification) {
            $groupId = $notification->GroupID;

            // Older rows predate the GroupID column - recover it from the
            // quiz the message names (format is always "New quiz scheduled:
            // {title}" or "Quiz updated: {title}", see QuizController).
            if ($groupId === null) {
                $title = preg_replace('/^(New quiz scheduled: |Quiz updated: )/', '', $notification->Message);
                $groupId = DB::table('Quiz')->where('Title', $title)->value('GroupID');
            }

            // Can't verify which group this was about at all - leave it
            // alone rather than guess.
            if ($groupId === null) {
                continue;
            }

            $isMember = DB::table('GroupStudent')
                ->where('GroupID', $groupId)
                ->where('UserID', $notification->UserID)
                ->exists();

            if (!$isMember) {
                DB::table('Notification')->where('NotificationID', $notification->NotificationID)->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible - these were notifications sent to students who
        // were never (or no longer) in the group; there's nothing correct
        // to restore.
    }
};
