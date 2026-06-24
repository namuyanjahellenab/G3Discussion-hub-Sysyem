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
   Schema::create('Topic', function (Blueprint $table) {
    
    $table->id('TopicID');
    
    
    $table->string('Title', 255);
    $table->string('Category', 100);
    
    // 3. FOREIGN KEY pointing to 'UserID' on the 'users' table
    $table->foreignId('CreatedBy')->constrained('User', 'UserID')->onDelete('cascade');
    
    
    $table->boolean('is_resolved')->default(false)->comment('Tracks if the question has been answered');
    
    $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
});
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
