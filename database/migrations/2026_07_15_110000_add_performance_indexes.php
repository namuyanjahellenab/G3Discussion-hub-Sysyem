<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Everything else the performance-audit list calls out (post_flags.PostID,
     * reply_flags.ReplyID, topic_exclusions.TopicID/UserID, Participation.GroupID,
     * Post.TopicID, Reply.PostID) already has an index — either from the unique
     * constraint or Laravel's automatic FK index via foreignId()->constrained().
     * Notification.UserID+Status was the one real gap: it only had a plain
     * UserID index, but every read (poll, unread count, "my questions") filters
     * on both columns together. Post/Reply.CreatedAt are added too since
     * AdminStatisticsController does daily whereDate() range scans on both.
     */
    public function up(): void
    {
        Schema::table('Notification', function (Blueprint $table) {
            $table->index(['UserID', 'Status']);
        });

        Schema::table('Post', function (Blueprint $table) {
            $table->index('CreatedAt');
        });

        Schema::table('Reply', function (Blueprint $table) {
            $table->index('CreatedAt');
        });
    }

    public function down(): void
    {
        Schema::table('Notification', function (Blueprint $table) {
            $table->dropIndex(['UserID', 'Status']);
        });

        Schema::table('Post', function (Blueprint $table) {
            $table->dropIndex(['CreatedAt']);
        });

        Schema::table('Reply', function (Blueprint $table) {
            $table->dropIndex(['CreatedAt']);
        });
    }
};
