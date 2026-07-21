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
<<<<<<<< HEAD:database/migrations/2026_07_14_090000_add_theme_and_quiz_duration_to_user_table.php
        Schema::table('user', function (Blueprint $table) {
            $table->string('ThemeColor', 20)->default('luna')->after('Role');
            $table->unsignedInteger('DefaultQuizDurationMinutes')->nullable()->after('ThemeColor');
========
        Schema::table('message', function (Blueprint $table) {
            $table->string('Attachment')->nullable()->after('body');
            $table->string('AttachmentType')->nullable()->after('Attachment');
>>>>>>>> b096b8061505adae84a1e243d68a722eda0206c9:database/migrations/2026_07_21_000000_add_attachment_to_message_table.php
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
<<<<<<<< HEAD:database/migrations/2026_07_14_090000_add_theme_and_quiz_duration_to_user_table.php
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn(['ThemeColor', 'DefaultQuizDurationMinutes']);
========
        Schema::table('message', function (Blueprint $table) {
            $table->dropColumn(['Attachment', 'AttachmentType']);
>>>>>>>> b096b8061505adae84a1e243d68a722eda0206c9:database/migrations/2026_07_21_000000_add_attachment_to_message_table.php
        });
    }
};
