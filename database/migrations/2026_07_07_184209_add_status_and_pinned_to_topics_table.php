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
    Schema::table('Topic', function (Blueprint $table) {
        $table->string('Status')->default('open'); // open, answered, discussion
        $table->boolean('IsPinned')->default(false);
    });
}

public function down(): void
{
    Schema::table('Topic', function (Blueprint $table) {
        $table->dropColumn(['Status', 'IsPinned']);
    });
}
};
