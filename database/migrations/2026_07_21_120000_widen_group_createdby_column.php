<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Group.CreatedBy stores the creator's UserName (a plain string, not a
     * foreign key) - it was still VARCHAR(20) from the original create_groups_table
     * migration even though User.UserName itself is VARCHAR(100), so any
     * longer username was silently truncated (or rejected under strict SQL
     * mode) when a group was created.
     */
    public function up(): void
    {
        Schema::table('Group', function (Blueprint $table) {
            $table->string('CreatedBy', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('Group', function (Blueprint $table) {
            $table->string('CreatedBy', 20)->change();
        });
    }
};
