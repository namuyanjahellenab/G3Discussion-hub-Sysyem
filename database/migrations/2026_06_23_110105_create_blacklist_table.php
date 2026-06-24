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
        Schema::create('Blacklist', function (Blueprint $table) {
            $table->id('BlacklistID');
    $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
    $table->dateTime('StartDate');
    $table->dateTime('EndDate');
    $table->string('Reason', 250);
            $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blacklist');
    }
};
