<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Group', function (Blueprint $table) {
            $table->string('GroupName', 100)->change();
            $table->string('Description', 500)->change();
        });
    }

    public function down(): void
    {
        Schema::table('Group', function (Blueprint $table) {
            $table->string('GroupName', 20)->change();
            $table->string('Description', 20)->change();
        });
    }
};