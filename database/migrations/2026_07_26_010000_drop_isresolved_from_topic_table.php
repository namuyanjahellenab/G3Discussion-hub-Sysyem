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
        // Confirmed dead: never in Topic::$fillable (so the one seeder line
        // that ever set it was already a silent no-op), never read anywhere.
        // Topic.Status (open/answered/discussion) is the real, live workflow
        // state - this column duplicated nothing anyone actually used.
        Schema::table('Topic', function (Blueprint $table) {
            $table->dropColumn('IsResolved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Topic', function (Blueprint $table) {
            $table->boolean('IsResolved')->default(false);
        });
    }
};
