<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Blacklist', function (Blueprint $table) {
            $table->enum('Type', ['Auto', 'Manual'])->default('Manual')->after('Reason');
            $table->unsignedBigInteger('IssuedBy')->nullable()->after('Type');

            $table->foreign('IssuedBy')->references('UserID')->on('User')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('Blacklist', function (Blueprint $table) {
            $table->dropForeign(['IssuedBy']);
            $table->dropColumn(['Type', 'IssuedBy']);
        });
    }
};