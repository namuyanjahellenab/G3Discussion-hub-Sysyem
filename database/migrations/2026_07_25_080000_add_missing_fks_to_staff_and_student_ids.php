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
        // LinkedUserID has always been read as a User reference in code
        // (LecturerStaffId::linkedUser()/StudentId::linkedUser(), and
        // normalize_legacy_admin_role.php's own join) but was never
        // DB-enforced. Null out any row pointing at a UserID that no
        // longer exists before adding the constraint, so this doesn't fail
        // on pre-existing dangling references on any environment.
        DB::statement('UPDATE LecturerStaffIDs SET LinkedUserID = NULL WHERE LinkedUserID IS NOT NULL AND LinkedUserID NOT IN (SELECT UserID FROM `User`)');
        DB::statement('UPDATE StudentIDs SET LinkedUserID = NULL WHERE LinkedUserID IS NOT NULL AND LinkedUserID NOT IN (SELECT UserID FROM `User`)');

        // onDelete('set null'), not the default RESTRICT - ProfileController::
        // destroy() (DELETE /profile) lets a user delete their own account
        // today, and almost every real student/lecturer registered with a
        // staff/student ID code. A plain RESTRICT FK would newly make that
        // delete throw a DB error where it silently succeeds today; 'set
        // null' preserves that existing behavior while still cleaning up
        // the pointer instead of leaving it dangling with an invalid ID.
        Schema::table('LecturerStaffIDs', function (Blueprint $table) {
            $table->foreign('LinkedUserID')->references('UserID')->on('User')->onDelete('set null');
        });

        Schema::table('StudentIDs', function (Blueprint $table) {
            $table->foreign('LinkedUserID')->references('UserID')->on('User')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('LecturerStaffIDs', function (Blueprint $table) {
            $table->dropForeign(['LinkedUserID']);
        });

        Schema::table('StudentIDs', function (Blueprint $table) {
            $table->dropForeign(['LinkedUserID']);
        });
    }
};
