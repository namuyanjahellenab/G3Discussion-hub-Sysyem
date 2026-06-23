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
        Schema::create('quiz_results', function (Blueprint $table) {
            $table->id('ResultID');
    $table->foreignId('QuizID')->constrained('quizzes', 'QuizID')->onDelete('cascade');
    $table->foreignId('UserID')->constrained('users', 'UserID')->onDelete('cascade');
    $table->decimal('Score', 5, 2);
    $table->dateTime('SubmissionTime');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_results');
    }
};
