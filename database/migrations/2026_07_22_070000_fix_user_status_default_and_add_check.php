<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The column defaulted to 'Active' regardless of whether the user had
        // ever actually done anything - a row inserted with no explicit
        // Status (e.g. a factory/seeder) came out "Active" despite having no
        // LastActive at all, contradicting the real rule this app already
        // applies everywhere else: students:process-inactivity treats a null
        // LastActive as inactive (see ProcessInactiveStudents::processStudent),
        // and User::recordActivity only ever sets 'Active' in response to a
        // real message/post/reply. 'Inactive' is the only default consistent
        // with "no real activity recorded yet".
        DB::statement("ALTER TABLE `User` ALTER COLUMN `Status` SET DEFAULT 'Inactive'");

        // The column only ever documented the 3 valid values in a comment -
        // nothing stopped an unexpected value (typo, stray API payload, etc)
        // from being written. Enforce it at the DB layer.
        DB::statement("ALTER TABLE `User` ADD CONSTRAINT `chk_user_status` CHECK (`Status` IN ('Active', 'Inactive', 'Blacklisted'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `User` DROP CONSTRAINT `chk_user_status`");
        DB::statement("ALTER TABLE `User` ALTER COLUMN `Status` SET DEFAULT 'Active'");
    }
};
