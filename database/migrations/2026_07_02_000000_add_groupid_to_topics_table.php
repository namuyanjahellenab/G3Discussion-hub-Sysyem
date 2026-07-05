<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Topic', function (Blueprint $table) {
            if (!Schema::hasColumn('Topic', 'GroupID')) {
                $table->foreignId('GroupID')->nullable()->after('CreatedBy');
                $table->index('GroupID');

                // If your topic rows already exist and you don't want cascade to delete,
                // adjust onDelete(...) as needed.
                $table->foreign('GroupID')->references('GroupID')->on('Group')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('Topic', function (Blueprint $table) {
            // Dropping FK column safely
            if (Schema::hasColumn('Topic', 'GroupID')) {
                $table->dropForeign(['GroupID']);
                $table->dropIndex(['GroupID']);
                $table->dropColumn('GroupID');
            }
        });
    }
};

