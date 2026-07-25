<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Warning', function (Blueprint $table) {
            $table->string('Reason', 250)->nullable()->after('WarningNo');
        });
    }

    public function down(): void
    {
        Schema::table('Warning', function (Blueprint $table) {
            $table->dropColumn('Reason');
        });
    }
};