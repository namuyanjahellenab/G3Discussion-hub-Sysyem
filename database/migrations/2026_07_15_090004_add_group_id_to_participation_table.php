<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Participation', function (Blueprint $table) {
            $table->foreignId('GroupID')->nullable()->after('UserID')->constrained('Group', 'GroupID')->onDelete('cascade');
        });

        // Existing rows predate per-group scoring and have no way to know
        // which group they belonged to. Drop them rather than guess — the
        // next post/reply a user makes will recreate an accurate, properly
        // group-scoped row via ParticipationService::recalculate().
        DB::table('Participation')->whereNull('GroupID')->delete();

        Schema::table('Participation', function (Blueprint $table) {
            $table->foreignId('GroupID')->nullable(false)->change();
            $table->unique(['UserID', 'GroupID']);
        });
    }

    public function down(): void
    {
        Schema::table('Participation', function (Blueprint $table) {
            $table->dropUnique(['UserID', 'GroupID']);
            $table->dropConstrainedForeignId('GroupID');
        });
    }
};
