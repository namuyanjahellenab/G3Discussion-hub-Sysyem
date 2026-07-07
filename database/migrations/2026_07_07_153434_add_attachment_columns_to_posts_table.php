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
        $table->string('Attachment')->nullable();
        $table->string('AttachmentType')->nullable();
    });
}

public function down(): void
{
    Schema::table('Post', function (Blueprint $table) {
        $table->dropColumn(['Attachment', 'AttachmentType']);
    });
}
};
