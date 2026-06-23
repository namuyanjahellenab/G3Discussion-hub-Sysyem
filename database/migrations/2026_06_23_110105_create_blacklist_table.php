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
        Schema::create('blacklist', function (Blueprint $table) {
            $table->id('BlacklistID');
    $table->foreignId('UserID')->constrained('users', 'UserID')->onDelete('cascade');
    $table->dateTime('StartDate');
    $table->dateTime('EndDate');
    $table->string('Reason', 250);
            $table->timestamps();
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
