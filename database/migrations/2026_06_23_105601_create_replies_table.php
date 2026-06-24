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
        Schema::create('Reply', function (Blueprint $table) {
    $table->id('ReplyID');
    $table->foreignId('PostID')->constrained('Post', 'PostID')->onDelete('cascade');
    $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
    $table->text('ReplyContent');
    $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replies');
    }
};
