<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Quiz', function (Blueprint $table) {
            $table->foreignId('GroupID')->nullable()->after('LecturerID')
                ->constrained('Group', 'GroupID')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('Quiz', function (Blueprint $table) {
            $table->dropForeign(['GroupID']);
            $table->dropColumn('GroupID');
        });
    }
};