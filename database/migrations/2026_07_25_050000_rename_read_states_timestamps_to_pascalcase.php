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
        // read_states was the only table in this app using Laravel's
        // default timestamps() - every other table uses CreatedAt/UpdatedAt.
        // Nothing reads these columns directly (ReadState only ever touches
        // UserID/EntityType/EntityID/LastReadItemId), so this is a pure
        // naming fix.
        Schema::table('read_states', function (Blueprint $table) {
            $table->renameColumn('created_at', 'CreatedAt');
            $table->renameColumn('updated_at', 'UpdatedAt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('read_states', function (Blueprint $table) {
            $table->renameColumn('CreatedAt', 'created_at');
            $table->renameColumn('UpdatedAt', 'updated_at');
        });
    }
};
