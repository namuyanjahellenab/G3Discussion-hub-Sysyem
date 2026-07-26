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
        // Notification previously had no way to say which group it was
        // about - just a UserID and a free-text Message - so a "New quiz
        // scheduled" notification couldn't be cleaned up when the recipient
        // later left that quiz's group. Nullable because most notification
        // Types (Reply, Warning, Blacklist, etc.) aren't group-scoped at all.
        Schema::table('Notification', function (Blueprint $table) {
            $table->foreignId('GroupID')->nullable()->after('UserID')->constrained('Group', 'GroupID')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Notification', function (Blueprint $table) {
            $table->dropForeign(['GroupID']);
            $table->dropColumn('GroupID');
        });
    }
};
