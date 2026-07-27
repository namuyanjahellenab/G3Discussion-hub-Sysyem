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
        // Matches this app's PascalCase column convention - Conversation's
        // own CreatedBy/ConversationID are already PascalCase; only this FK
        // column broke from it. Unrelated to the separate 'group_id' HTTP
        // query-string convention used elsewhere in this app (statistics/
        // participation-criteria/messages filtering) - that stays untouched.
        Schema::table('Conversation', function (Blueprint $table) {
            $table->renameColumn('group_id', 'GroupID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Conversation', function (Blueprint $table) {
            $table->renameColumn('GroupID', 'group_id');
        });
    }
};
