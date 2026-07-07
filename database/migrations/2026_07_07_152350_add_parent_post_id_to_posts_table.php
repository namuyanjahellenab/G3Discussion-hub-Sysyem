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
    Schema::table('Post', function (Blueprint $table) {
        $table->unsignedBigInteger('ParentPostID')->nullable();
        $table->foreign('ParentPostID')->references('PostID')->on('Post')->onDelete('cascade');
    });
}

public function down(): void
{
    Schema::table('Post', function (Blueprint $table) {
        $table->dropForeign(['ParentPostID']);
        $table->dropColumn('ParentPostID');
    });
}
};