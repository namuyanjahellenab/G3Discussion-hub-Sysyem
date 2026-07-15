<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participation_criteria', function (Blueprint $table) {
            $table->id('ParticipationCriteriaID');
            // Null GroupID = platform-wide default, used when a group has no
            // criteria row of its own.
            $table->foreignId('GroupID')->nullable()->constrained('Group', 'GroupID')->onDelete('cascade');
            $table->unsignedSmallInteger('PointsPerPost')->default(2);
            $table->unsignedSmallInteger('PointsPerReply')->default(1);
            $table->unsignedSmallInteger('PointsPerAcceptedAnswer')->default(0);
            $table->timestamp('CreatedAt')->useCurrent();
            $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique('GroupID');
        });

        // Seed the platform-wide default row (GroupID = null) with the same
        // values the old hardcoded formula used, so existing scores don't
        // silently change the moment this ships.
        DB::table('participation_criteria')->insert([
            'GroupID' => null,
            'PointsPerPost' => 2,
            'PointsPerReply' => 1,
            'PointsPerAcceptedAnswer' => 0,
            'CreatedAt' => now(),
            'UpdatedAt' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_criteria');
    }
};
