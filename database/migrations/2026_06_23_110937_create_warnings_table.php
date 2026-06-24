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
        Schema::create('Warning', function (Blueprint $table) {
            $table->id('WarningID');
    $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
    $table->integer('WarningNo'); // 1 or 2
    $table->dateTime('ExpiryDate');
            $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warnings');
    }
};
