<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('GroupStudent', function (Blueprint $table) {
            if (!Schema::hasColumn('GroupStudent', 'LastViewedAt')) {
                $table->timestamp('LastViewedAt')->nullable()->after('Status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('GroupStudent', function (Blueprint $table) {
            if (Schema::hasColumn('GroupStudent', 'LastViewedAt')) {
                $table->dropColumn('LastViewedAt');
            }
        });
    }
};
