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
    Schema::table('Reply', function (Blueprint $table) {
        $table->boolean('IsAccepted')->default(false);
    });
}

public function down(): void
{
    Schema::table('Reply', function (Blueprint $table) {
        $table->dropColumn('IsAccepted');
    });
}
};
