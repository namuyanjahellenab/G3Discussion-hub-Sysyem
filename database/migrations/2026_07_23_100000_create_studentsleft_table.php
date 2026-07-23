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
        // Snapshot of a student's data at the moment they leave a group -
        // deliberately NOT a foreign key to User/Group (a left student's
        // account or the group itself could later be deleted entirely, and
        // this record must survive that), and deliberately separate from the
        // live GroupStudent/Post/Reply/QuizResult tables so the Statistics
        // screen's real-time group queries can simply exclude whichever
        // (UserID, GroupID) pairs show up here, without needing to touch or
        // delete any of that underlying historical data.
        Schema::create('Studentsleft', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('UserID');
            $table->string('UserName');
            $table->string('Email')->nullable();
            $table->string('StudentIDNumber')->nullable();
            $table->unsignedBigInteger('GroupID');
            $table->string('GroupName');
            $table->decimal('TotalMarks', 8, 2)->default(0);
            $table->unsignedInteger('PostCount')->default(0);
            $table->unsignedInteger('ReplyCount')->default(0);
            $table->decimal('ParticipationScore', 5, 2)->default(0);
            $table->timestamp('LeftAt')->useCurrent();

            $table->index(['UserID', 'GroupID']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Studentsleft');
    }
};
