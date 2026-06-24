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
        Schema::create('Registration', function (Blueprint $table) {
            $table->id('RegistrationID');
    $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
    $table->boolean('AcceptedRules')->default(false);
            $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
