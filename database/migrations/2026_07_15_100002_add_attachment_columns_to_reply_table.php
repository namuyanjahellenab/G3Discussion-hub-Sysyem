<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Reply', function (Blueprint $table) {
            $table->string('Attachment')->nullable()->after('ParentReplyID');
            $table->string('AttachmentType')->nullable()->after('Attachment');
        });
    }

    public function down(): void
    {
        Schema::table('Reply', function (Blueprint $table) {
            $table->dropColumn(['Attachment', 'AttachmentType']);
        });
    }
};
