<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tracks "furthest reply/message id this user has actually opened this
// thread and seen" per Topic/Conversation, mirroring the desktop client's
// local ReadState table (DatabaseManager) so both surfaces show/hide the
// same unread badges and NEW MESSAGES divider. EntityType is "Topic" or
// "Conversation"; EntityID is that TopicID/ConversationID.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('read_states', function (Blueprint $table) {
            $table->id('ReadStateID');
            $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
            $table->string('EntityType');
            $table->unsignedBigInteger('EntityID');
            $table->unsignedBigInteger('LastReadItemId')->default(0);
            $table->timestamps();
            $table->unique(['UserID', 'EntityType', 'EntityID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('read_states');
    }
};
