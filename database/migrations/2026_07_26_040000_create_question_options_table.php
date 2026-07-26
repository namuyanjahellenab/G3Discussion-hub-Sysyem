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
        // Question.Options previously stored a JSON-encoded array of MCQ
        // choices in one text column - a repeating group (1NF violation).
        // This is the proper normalized home for that data: one row per
        // option, ordered by Position (the existing code matches the
        // correct answer by exact text, not index, but display/entry order
        // should still stay stable rather than reshuffle).
        Schema::create('question_options', function (Blueprint $table) {
            $table->id('QuestionOptionID');
            $table->foreignId('QuestionID')->constrained('Question', 'QuestionID')->onDelete('cascade');
            $table->string('OptionText');
            $table->unsignedInteger('Position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};
