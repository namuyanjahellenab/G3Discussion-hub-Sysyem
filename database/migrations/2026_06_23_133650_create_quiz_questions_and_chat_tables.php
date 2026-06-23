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
    // 1. device_states table
    Schema::create('device_states', function (Blueprint $table) {
        $table->id('DeviceID');
        $table->foreignId('UserID')->constrained('users', 'UserID')->onDelete('cascade');
        $table->dateTime('LastSyncAt');
        $table->string('SyncStatus', 20);
        $table->timestamps();
    });

    // 2. sync_queues table
    Schema::create('sync_queues', function (Blueprint $table) {
        $table->id('SyncQueueID');
        $table->foreignId('DeviceID')->constrained('device_states', 'DeviceID')->onDelete('cascade');
        $table->string('EntityType', 50);
        $table->integer('EntityID');
        $table->string('Operation', 20);
        $table->text('Payload');
        $table->boolean('IsDirty')->default(true);
        $table->timestamps();
    });

    // 3. conversations table
    Schema::create('conversations', function (Blueprint $table) {
        $table->id('ConversationID');
        $table->string('Type', 20);
        $table->foreignId('CreatedBy')->constrained('users', 'UserID')->onDelete('cascade');
        $table->timestamps();
    });

    // 4. conversation_members table
    Schema::create('conversation_members', function (Blueprint $table) {
        $table->id('MemberID');
        $table->foreignId('ConversationID')->constrained('conversations', 'ConversationID')->onDelete('cascade');
        $table->foreignId('UserID')->constrained('users', 'UserID')->onDelete('cascade');
        $table->dateTime('JoinedAt');
        $table->timestamps();
    });

    // 5. conversation_exclusions table
    Schema::create('conversation_exclusions', function (Blueprint $table) {
        $table->id('ExclusionID');
        $table->foreignId('ConversationID')->constrained('conversations', 'ConversationID')->onDelete('cascade');
        $table->foreignId('UserID')->constrained('users', 'UserID')->onDelete('cascade');
        $table->foreignId('ExcludedBy')->constrained('users', 'UserID')->onDelete('cascade');
        $table->timestamps();
    });

    // 6. questions table
    Schema::create('questions', function (Blueprint $table) {
        $table->id('QuestionID');
        $table->foreignId('QuizID')->constrained('quizzes', 'QuizID')->onDelete('cascade');
        $table->text('QuestionText');
        $table->string('QuestionType', 20);
        $table->text('Options')->nullable();
        $table->string('CorrectAnswer', 255)->nullable();
        $table->decimal('Marks', 5, 2);
        $table->timestamps();
    });

    // 7. answers table
    Schema::create('answers', function (Blueprint $table) {
        $table->id('AnswerID');
        $table->foreignId('QuestionID')->constrained('questions', 'QuestionID')->onDelete('cascade');
        $table->foreignId('ResultID')->constrained('quiz_results', 'ResultID')->onDelete('cascade');
        $table->foreignId('UserID')->constrained('users', 'UserID')->onDelete('cascade');
        $table->text('ResponseText');
        $table->boolean('IsCorrect');
        $table->timestamps();
    });

Schema::create('messages', function (Blueprint $table) {
    // 1. Map to your custom primary key (MessageID)
    $table->id('MessageID');
    
    // 2. Explicitly map to your plural tables and custom primary keys
    $table->foreignId('TopicID')->constrained('topics', 'TopicID')->onDelete('cascade');
    $table->foreignId('user_id')->constrained('users', 'UserID')->onDelete('cascade');
    
    // 3. Keep your functional requirement fields
    $table->text('body');
    $table->boolean('is_spam')->default(false); // For Requirement #1 Content Moderation
    
    $table->timestamps();
});
   }   /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions_and_chat_tables');
    }
};
