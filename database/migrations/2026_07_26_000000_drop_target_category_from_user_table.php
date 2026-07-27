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
        // Confirmed dead: never in User::$fillable, never read or written
        // anywhere in the app outside the migration that added it.
        Schema::table('User', function (Blueprint $table) {
            $table->dropColumn('TargetCategory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('User', function (Blueprint $table) {
            $table->string('TargetCategory', 100)->nullable()->after('Role');
        });
    }
};
