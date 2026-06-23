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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('NotificationID');
    $table->foreignId('UserID')->constrained('users', 'UserID')->onDelete('cascade');
    $table->text('Message');
    $table->boolean('Status')->default(false); // false = unread, true = read
    $table->string('Type', 50); // Warning, Quiz, System alert
    
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
