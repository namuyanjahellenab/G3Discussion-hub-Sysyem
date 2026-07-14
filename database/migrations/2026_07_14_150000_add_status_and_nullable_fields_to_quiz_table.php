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
        Schema::table('Quiz', function (Blueprint $table) {
            $table->string('Status', 20)->default('scheduled')->after('TargetCategory');
        });

        Schema::table('Quiz', function (Blueprint $table) {
            $table->dateTime('StartTime')->nullable()->change();
            $table->integer('Duration')->nullable()->change();
            $table->string('TargetCategory', 100)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Quiz', function (Blueprint $table) {
            $table->dropColumn('Status');
        });

        Schema::table('Quiz', function (Blueprint $table) {
            $table->dateTime('StartTime')->nullable(false)->change();
            $table->integer('Duration')->nullable(false)->change();
            $table->string('TargetCategory', 100)->nullable(false)->change();
        });
    }
};
