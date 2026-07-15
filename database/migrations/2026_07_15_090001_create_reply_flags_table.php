<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reply_flags', function (Blueprint $table) {
            $table->id('ReplyFlagID');
            $table->foreignId('ReplyID')->constrained('Reply', 'ReplyID')->onDelete('cascade');
            $table->foreignId('FlaggedByUserID')->constrained('User', 'UserID')->onDelete('cascade');
            $table->string('Reason', 250)->nullable();
            $table->timestamp('CreatedAt')->useCurrent();
            $table->unique(['ReplyID', 'FlaggedByUserID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reply_flags');
    }
};
