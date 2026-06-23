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
        Schema::create('group_students', function (Blueprint $table) {
            $table->id('StudentID');
    $table->foreignId('GroupID')->constrained('groups', 'GroupID')->onDelete('cascade');
    $table->foreignId('UserID')->constrained('users', 'UserID')->onDelete('cascade');
    $table->string('Status', 20); // Active or inactive
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_students');
    }
};
