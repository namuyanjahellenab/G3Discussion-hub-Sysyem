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
        Schema::create('recommendations', function (Blueprint $table) {
          $table->id('RecommendationID');
    $table->foreignId('UserID')->constrained('users', 'UserID')->onDelete('cascade');
    $table->foreignId('TopicID')->constrained('topics', 'TopicID')->onDelete('cascade');
    $table->decimal('RelevanceScore', 5, 2);  
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
