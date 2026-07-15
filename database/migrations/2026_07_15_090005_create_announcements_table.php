<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id('AnnouncementID');
            $table->foreignId('AuthorID')->constrained('User', 'UserID')->onDelete('cascade');
            // Null GroupID = campus-wide (admin-only).
            $table->foreignId('GroupID')->nullable()->constrained('Group', 'GroupID')->onDelete('cascade');
            $table->text('Message');
            $table->timestamp('CreatedAt')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
