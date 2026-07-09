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
            if (!Schema::hasColumn('Post', 'Attachment')) {
                $table->string('Attachment')->nullable();
            }
            if (!Schema::hasColumn('Post', 'AttachmentType')) {
                $table->string('AttachmentType')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('Post', function (Blueprint $table) {
            if (Schema::hasColumn('Post', 'Attachment')) {
                $table->dropColumn('Attachment');
            }
            if (Schema::hasColumn('Post', 'AttachmentType')) {
                $table->dropColumn('AttachmentType');
            }
        });
    }
};
