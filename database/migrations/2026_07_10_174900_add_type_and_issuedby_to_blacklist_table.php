<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blacklist', function (Blueprint $table) {
            $table->enum('Type', ['Auto', 'Manual'])->default('Manual')->after('Reason');
            $table->unsignedBigInteger('IssuedBy')->nullable()->after('Type');

            $table->foreign('IssuedBy')->references('UserID')->on('user')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blacklist', function (Blueprint $table) {
            $table->dropForeign(['IssuedBy']);
            $table->dropColumn(['Type', 'IssuedBy']);
        });
    }
};