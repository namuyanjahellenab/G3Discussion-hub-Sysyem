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
        Schema::table('users', function (Blueprint $table) {
            // Add new columns if they don't exist
            if (!Schema::hasColumn('users', 'full_name')) {
                $table->string('full_name')->after('name')->nullable();
            }
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->unique()->after('email')->nullable();
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['student', 'lecturer', 'administrator'])->after('username')->default('student');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['active', 'suspended', 'blacklisted', 'pending'])->after('role')->default('active');
            }
            if (!Schema::hasColumn('users', 'warnings')) {
                $table->integer('warnings')->after('status')->default(0);
            }
            if (!Schema::hasColumn('users', 'last_active')) {
                $table->timestamp('last_active')->after('warnings')->nullable();
            }
            if (!Schema::hasColumn('users', 'rules_accepted')) {
                $table->boolean('rules_accepted')->after('last_active')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'full_name',
                'username',
                'role',
                'status',
                'warnings',
                'last_active',
                'rules_accepted'
            ]);
        });
    }
};
