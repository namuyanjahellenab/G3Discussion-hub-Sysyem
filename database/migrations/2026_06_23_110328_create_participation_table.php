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
        Schema::create('Participation', function (Blueprint $table) {
            $table->id('ParticipationID');
    $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
    $table->integer('PostCount')->default(0);
    $table->integer('ReplyCount')->default(0);
    $table->decimal('ParticipationScore', 5, 2)->default(0.00);
            $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participation');
    }
};
