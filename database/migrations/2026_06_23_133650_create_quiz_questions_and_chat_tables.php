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
    Schema::create('DeviceState', function (Blueprint $table) {
        $table->id('DeviceID');
        $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
        $table->dateTime('LastSyncAt');
        $table->string('SyncStatus', 20);
        $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
    });

    // 2. sync_queues table
    Schema::create('SyncQueue', function (Blueprint $table) {
        $table->id('SyncQueueID');
        $table->foreignId('DeviceID')->constrained('DeviceState', 'DeviceID')->onDelete('cascade');
        $table->string('EntityType', 50);
        $table->integer('EntityID');
        $table->string('Operation', 20);
        $table->text('Payload');
        $table->boolean('IsDirty')->default(true);
        $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
    });

    // 3. conversations table
    Schema::create('Conversation', function (Blueprint $table) {
        $table->id('ConversationID');
        $table->string('Type', 20);
        $table->foreignId('CreatedBy')->constrained('User', 'UserID')->onDelete('cascade');
        $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
    });

    // 4. conversation_members table
    Schema::create('ConversationMember', function (Blueprint $table) {
        $table->id('MemberID');
        $table->foreignId('ConversationID')->constrained('Conversation', 'ConversationID')->onDelete('cascade');
        $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
        $table->dateTime('JoinedAt');
        $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
    });

    // 5. conversation_exclusions table
    Schema::create('ConversationExclusion', function (Blueprint $table) {
        $table->id('ExclusionID');
        $table->foreignId('ConversationID')->constrained('Conversation', 'ConversationID')->onDelete('cascade');
        $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
        $table->foreignId('ExcludedBy')->constrained('User', 'UserID')->onDelete('cascade');
        $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
    });

    // 6. questions table
    Schema::create('Question', function (Blueprint $table) {
        $table->id('QuestionID');
        $table->foreignId('QuizID')->constrained('Quiz', 'QuizID')->onDelete('cascade');
        $table->text('QuestionText');
        $table->string('QuestionType', 20);
        $table->text('Options')->nullable();
        $table->string('CorrectAnswer', 255)->nullable();
        $table->decimal('Marks', 5, 2);
        $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
    });

    // 7. answers table
    Schema::create('Answer', function (Blueprint $table) {
        $table->id('AnswerID');
        $table->foreignId('QuestionID')->constrained('Question', 'QuestionID')->onDelete('cascade');
        $table->foreignId('ResultID')->constrained('QuizResult', 'ResultID')->onDelete('cascade');
        $table->foreignId('UserID')->constrained('User', 'UserID')->onDelete('cascade');
        $table->text('ResponseText');
        $table->boolean('IsCorrect');
        $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
    });

Schema::create('Message', function (Blueprint $table) {
    $table->id('MessageID');
    
    
    $table->foreignId('TopicID')->constrained('Topic', 'TopicID')->onDelete('cascade');
    $table->foreignId('user_id')->constrained('User', 'UserID')->onDelete('cascade');
    
    
    $table->text('body');
    $table->boolean('is_spam')->default(false); // For Requirement #1 Content Moderation
    
    $table->timestamp('CreatedAt')->useCurrent();
    $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
});
   }   /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions_and_chat_tables');
    }
};
