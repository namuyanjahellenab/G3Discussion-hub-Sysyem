<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('User', function (Blueprint $table) {
            $table->string('TargetCategory', 100)->nullable()->after('Role');
        });
    }
    public function down(): void {
        Schema::table('User', function (Blueprint $table) {
            $table->dropColumn('TargetCategory');
        });
    }
};