<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_exclusions', function (Blueprint $table) {
            $table->id('TopicExclusionID');
            $table->foreignId('TopicID')->constrained('Topic', 'TopicID')->onDelete('cascade');
            $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
            $table->unique(['TopicID', 'UserID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_exclusions');
    }
};
