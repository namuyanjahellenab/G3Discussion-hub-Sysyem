<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('User', function (Blueprint $table) {
            $table->string('Handle', 100)->nullable()->unique()->after('UserName');
            $table->boolean('RulesAccepted')->default(false)->after('Status');
            $table->timestamp('LastActive')->nullable()->after('RulesAccepted');
        });
    }

    public function down(): void
    {
        Schema::table('User', function (Blueprint $table) {
            $table->dropColumn(['Handle', 'RulesAccepted', 'LastActive']);
        });
    }
};