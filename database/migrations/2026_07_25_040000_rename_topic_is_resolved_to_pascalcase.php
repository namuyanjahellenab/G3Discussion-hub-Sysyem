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
        // Matches this app's PascalCase column convention (UserID, GroupID,
        // Status, IsPinned, ...) - this was the one snake_case holdout on
        // Topic. Already effectively unused (not in Topic::$fillable), so
        // this is a pure naming fix with nothing behind it to break.
        Schema::table('Topic', function (Blueprint $table) {
            $table->renameColumn('is_resolved', 'IsResolved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Topic', function (Blueprint $table) {
            $table->renameColumn('IsResolved', 'is_resolved');
        });
    }
};
