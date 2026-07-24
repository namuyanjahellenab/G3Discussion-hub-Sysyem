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
            $table->unsignedBigInteger('ParentMessageID')->nullable()->after('user_id');
            $table->foreign('ParentMessageID')->references('MessageID')->on('message')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message', function (Blueprint $table) {
            $table->dropForeign(['ParentMessageID']);
            $table->dropColumn('ParentMessageID');
        });
    }
};
