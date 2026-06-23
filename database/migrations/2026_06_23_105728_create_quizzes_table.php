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
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id('QuizID');
    // Maps LecturerID to UserID in users table
    $table->foreignId('LecturerID')->constrained('users', 'UserID')->onDelete('cascade');
    $table->string('Title', 255);
    $table->dateTime('StartTime');
    $table->integer('Duration'); // minutes
    $table->string('TargetCategory', 100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
