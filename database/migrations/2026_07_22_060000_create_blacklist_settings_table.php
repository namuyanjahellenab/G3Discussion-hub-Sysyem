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
        Schema::create('blacklist_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('InactivityValue')->default(7);
            $table->string('InactivityUnit', 10)->default('days');
            $table->boolean('AutoBlacklistEnabled')->default(false);
            $table->unsignedInteger('FirstWarningValue')->nullable();
            $table->string('FirstWarningUnit', 10)->nullable();
            $table->unsignedInteger('SecondWarningValue')->nullable();
            $table->string('SecondWarningUnit', 10)->nullable();
            $table->unsignedInteger('BlacklistAfterSecondWarningValue')->nullable();
            $table->string('BlacklistAfterSecondWarningUnit', 10)->nullable();
            $table->unsignedInteger('BlacklistDurationValue')->nullable();
            $table->string('BlacklistDurationUnit', 10)->nullable();
            $table->timestamp('CreatedAt')->useCurrent();
            $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blacklist_settings');
    }
};
