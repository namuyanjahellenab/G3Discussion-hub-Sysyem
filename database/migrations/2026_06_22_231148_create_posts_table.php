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
        Schema::create('Post', function (Blueprint $table) {
            $table->id('PostID');
            $table->foreignId('TopicID')->constrained('Topic', 'TopicID')->onDelete('cascade');
            $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
            $table->unsignedBigInteger('ParentPostID')->nullable();
            $table->text('Content')->nullable();
            $table->string('Attachment')->nullable();
            $table->string('AttachmentType')->nullable();
            $table->timestamp('CreatedAt')->useCurrent();
            $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->foreign('ParentPostID')->references('PostID')->on('Post')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
