<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('LecturerStaffIDs', function (Blueprint $table) {
            $table->id('StaffCodeID');
            $table->string('StaffIDNumber', 50)->unique();
            $table->boolean('IsUsed')->default(false);
            $table->unsignedBigInteger('LinkedUserID')->nullable();
            $table->timestamp('CreatedAt')->useCurrent();
            $table->timestamp('UpdatedAt')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LecturerStaffIDs');
    }
};