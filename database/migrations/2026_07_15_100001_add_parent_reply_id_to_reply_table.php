<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reply', function (Blueprint $table) {
            $table->unsignedBigInteger('ParentReplyID')->nullable()->after('PostID');
            $table->foreign('ParentReplyID')->references('ReplyID')->on('Reply')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('reply', function (Blueprint $table) {
            $table->dropForeign(['ParentReplyID']);
            $table->dropColumn('ParentReplyID');
        });
    }
};
