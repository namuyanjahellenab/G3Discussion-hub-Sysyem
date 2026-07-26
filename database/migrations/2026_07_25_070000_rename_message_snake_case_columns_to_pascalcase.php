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
        // Matches this app's PascalCase column convention - every other
        // Message column (ConversationID, ParentMessageID, IsFlagged,
        // Attachment, ...) already follows it; only these three didn't.
        // The public-facing contract (API JSON keys, the ChatMessageSent
        // broadcast payload) keeps the old snake_case names alongside new
        // PascalCase ones added separately - only the internal
        // column/model/query layer changes here.
        Schema::table('message', function (Blueprint $table) {
            $table->renameColumn('user_id', 'UserID');
            $table->renameColumn('body', 'Body');
            $table->renameColumn('is_spam', 'IsSpam');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message', function (Blueprint $table) {
            $table->renameColumn('UserID', 'user_id');
            $table->renameColumn('Body', 'body');
            $table->renameColumn('IsSpam', 'is_spam');
        });
    }
};
