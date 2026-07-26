<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Group.CreatedBy has always been a plain string (a UserName), never
        // a real foreign key - and never actually read anywhere in the app
        // (confirmed by tracing every reference). The two places that write
        // it also disagreed on what to store: AdminGroupController wrote a
        // UserName, DiscussionHubPageController wrote a UserID. Converting
        // to a real FK requires resolving the existing string values first.
        Schema::table('Group', function (Blueprint $table) {
            $table->unsignedBigInteger('CreatedByUserID')->nullable()->after('CreatedBy');
        });

        // Resolve each existing CreatedBy string to the matching User by
        // UserName. A row whose CreatedBy doesn't match any User.UserName
        // (e.g. already-numeric, or the user was since renamed/deleted)
        // simply ends up NULL here - the column is nullable specifically to
        // accommodate that rather than fail the migration.
        DB::statement('
            UPDATE `Group` g
            LEFT JOIN `User` u ON u.UserName = g.CreatedBy
            SET g.CreatedByUserID = u.UserID
        ');

        Schema::table('Group', function (Blueprint $table) {
            $table->dropColumn('CreatedBy');
        });

        Schema::table('Group', function (Blueprint $table) {
            $table->renameColumn('CreatedByUserID', 'CreatedBy');
        });

        // onDelete('set null'), not the default RESTRICT - matches the
        // LecturerStaffIDs/StudentIDs FK pattern from the previous session.
        // Nothing reads this column today, so there's no display to keep
        // consistent, but a deleted user's groups should keep existing
        // rather than have their creation blocked/cascaded away.
        Schema::table('Group', function (Blueprint $table) {
            $table->foreign('CreatedBy')->references('UserID')->on('User')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Group', function (Blueprint $table) {
            $table->dropForeign(['CreatedBy']);
        });

        Schema::table('Group', function (Blueprint $table) {
            $table->string('CreatedByName', 100)->nullable()->after('CreatedBy');
        });

        DB::statement('
            UPDATE `Group` g
            LEFT JOIN `User` u ON u.UserID = g.CreatedBy
            SET g.CreatedByName = u.UserName
        ');

        Schema::table('Group', function (Blueprint $table) {
            $table->dropColumn('CreatedBy');
        });

        Schema::table('Group', function (Blueprint $table) {
            $table->renameColumn('CreatedByName', 'CreatedBy');
        });
    }
};
