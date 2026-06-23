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
        Schema::create('users', function (Blueprint $table) {
    // 1. Primary Key mapped to your data dictionary
    $table->id('UserID'); 
    
    // 2. Custom attributes from your data dictionary
    $table->string('UserName', 100);
    $table->string('Email', 150)->unique();
    $table->string('PasswordHash', 255);
    $table->string('Role', 20)->comment('Student, Lecturer, Admin');    
    $table->string('Status', 20)->default('Active')->comment('Active, Inactive, Blacklisted');  
    
    // 3. Required Laravel Breeze security items
    $table->timestamp('email_verified_at')->nullable();
    $table->rememberToken();
    $table->timestamps(); 
});


        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
