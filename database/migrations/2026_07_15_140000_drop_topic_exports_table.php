<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PDF export went back to synchronous generation (direct download response,
// no queued job) — the pending/ready/failed tracking this table existed for
// is no longer needed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('topic_exports');
    }

    public function down(): void
    {
        Schema::create('topic_exports', function (Blueprint $table) {
            $table->id('TopicExportID');
            $table->foreignId('TopicID')->constrained('Topic', 'TopicID')->onDelete('cascade');
            $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
            $table->string('Status', 20)->default('pending');
            $table->string('FilePath')->nullable();
            $table->timestamp('CreatedAt')->useCurrent();
            $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
        });
    }
};
