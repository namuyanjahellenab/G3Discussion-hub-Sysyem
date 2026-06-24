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
        Schema::create('TopicClassification', function (Blueprint $table) {
            $table->id('ClassificationID');
    // Your dictionary says TopicID here maps to a topic
    $table->foreignId('TopicID')->constrained('Topic', 'TopicID')->onDelete('cascade');
    $table->string('PredictedCategory', 100);
    $table->decimal('ConfidenceScore', 5, 2);
            $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topic_classifications');
    }
};
