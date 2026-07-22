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
        // Laravel's DatabaseTokenRepository (Password::sendResetLink())
        // reads/writes this table directly, bypassing Eloquent entirely, and
        // has "created_at" hardcoded - unlike every other table here, its
        // column name can't be adapted to this project's PascalCase
        // convention. This table was created straight in the DB following
        // that convention anyway (no migration ever tracked it), which is
        // why "Forgot Password" 500'd with "Unknown column 'created_at'".
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->renameColumn('CreatedAt', 'created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->renameColumn('created_at', 'CreatedAt');
        });
    }
};
