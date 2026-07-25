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
    Schema::table('Message', function (Blueprint $table) {
        $table->unsignedBigInteger('TopicID')->nullable()->change();
    });
}

public function down(): void
{
    Schema::table('Message', function (Blueprint $table) {
        $table->unsignedBigInteger('TopicID')->nullable(false)->change();
    });
}
};
