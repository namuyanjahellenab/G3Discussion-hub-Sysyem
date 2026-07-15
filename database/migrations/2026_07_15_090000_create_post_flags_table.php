<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_flags', function (Blueprint $table) {
            $table->id('PostFlagID');
            $table->foreignId('PostID')->constrained('Post', 'PostID')->onDelete('cascade');
            $table->foreignId('FlaggedByUserID')->constrained('User', 'UserID')->onDelete('cascade');
            $table->string('Reason', 250)->nullable();
            $table->timestamp('CreatedAt')->useCurrent();
            $table->unique(['PostID', 'FlaggedByUserID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_flags');
    }
};
