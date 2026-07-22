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
        Schema::create('user_inactivity_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('UserID')->unique()->constrained('User', 'UserID')->onDelete('cascade');
            $table->dateTime('FirstWarningAt')->nullable();
            $table->dateTime('SecondWarningAt')->nullable();
            $table->timestamp('CreatedAt')->useCurrent();
            $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_inactivity_warnings');
    }
};
