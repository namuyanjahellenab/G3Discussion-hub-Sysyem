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
    Schema::table('message', function (Blueprint $table) {
        $table->unsignedBigInteger('ConversationID')->nullable()->after('TopicID');
        $table->foreign('ConversationID')->references('ConversationID')->on('conversation')->onDelete('cascade');
    });
}

public function down(): void
{
    Schema::table('message', function (Blueprint $table) {
        $table->dropForeign(['ConversationID']);
        $table->dropColumn('ConversationID');
    });
}
};
