<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Reply', function (Blueprint $table) {
            $table->id('ReplyID');
            $table->foreignId('PostID')->constrained('Post', 'PostID')->onDelete('cascade');
            $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
            $table->text('ReplyContent');
            $table->boolean('IsAccepted')->default(false);
            $table->unsignedBigInteger('QuotedReplyID')->nullable();
            $table->foreign('QuotedReplyID')->references('ReplyID')->on('Reply')->nullOnDelete();
            $table->timestamp('CreatedAt')->useCurrent();
            $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Reply');
    }
};
