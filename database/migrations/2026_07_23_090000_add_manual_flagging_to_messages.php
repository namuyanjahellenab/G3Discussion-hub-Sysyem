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
        // Mirrors post_flags/reply_flags - a member reporting a message is a
        // separate concept from is_spam (the ML gateway's own automatic
        // detection, set in GroupChatService::send). IsFlagged/FlaggedReason
        // on the message itself is a derived cache of message_flags, exactly
        // like Post.IsFlagged/Reply.IsFlagged already work.
        Schema::table('message', function (Blueprint $table) {
            $table->boolean('IsFlagged')->default(false)->after('is_spam');
            $table->string('FlaggedReason', 250)->nullable()->after('IsFlagged');
        });

        Schema::create('message_flags', function (Blueprint $table) {
            $table->id('MessageFlagID');
            $table->foreignId('MessageID')->constrained('message', 'MessageID')->onDelete('cascade');
            $table->foreignId('FlaggedByUserID')->constrained('User', 'UserID')->onDelete('cascade');
            $table->string('Reason', 250)->nullable();
            $table->timestamp('CreatedAt')->useCurrent();
            $table->unique(['MessageID', 'FlaggedByUserID']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_flags');

        Schema::table('message', function (Blueprint $table) {
            $table->dropColumn(['IsFlagged', 'FlaggedReason']);
        });
    }
};
