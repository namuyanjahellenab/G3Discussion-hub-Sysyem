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
    Schema::table('Conversation', function (Blueprint $table) {
        $table->unsignedBigInteger('group_id')->nullable()->after('Type');
        $table->foreign('group_id')->references('GroupID')->on('Group')->onDelete('cascade');
    });
}

public function down(): void
{
    Schema::table('Conversation', function (Blueprint $table) {
        $table->dropForeign(['group_id']);
        $table->dropColumn('group_id');
    });
}};