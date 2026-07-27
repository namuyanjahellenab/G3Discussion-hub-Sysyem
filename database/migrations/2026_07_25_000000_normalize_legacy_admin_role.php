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
        // AdminLecturerStaffController::promote() used to write Role =
        // 'Admin' instead of 'Administrator' - the value every actual admin
        // check in the app (EnsureUserIsAdmin middleware, DashboardController,
        // AdminStatisticsController, etc.) looks for. Any user promoted
        // before that was fixed is stuck with a Role none of those checks
        // recognize, so DashboardController::index() falls past its
        // Lecturer/Administrator branches straight into the Student branch -
        // a previously-promoted lecturer's dashboard silently rendering as a
        // student's. This repairs any such row still sitting in that state.
        DB::table('User')->where('Role', 'Admin')->update(['Role' => 'Administrator']);

        // These same rows also predate PreviousRole being added to User's
        // $fillable, so promote() never actually recorded what they were
        // promoted from (it was silently dropped by mass-assignment
        // protection) - PreviousRole reads NULL for them even though they
        // were, in fact, promoted. Where a used LecturerStaffId links back to
        // one of these users, that's solid evidence their original role was
        // Lecturer, restoring the same PreviousRole promote() would set
        // today and re-enabling the admin<->lecturer dashboard toggle for
        // them.
        DB::table('User')
            ->where('Role', 'Administrator')
            ->whereNull('PreviousRole')
            ->whereIn('UserID', function ($query) {
                $query->select('LinkedUserID')
                    ->from('LecturerStaffIDs')
                    ->where('IsUsed', true)
                    ->whereNotNull('LinkedUserID');
            })
            ->update(['PreviousRole' => 'Lecturer']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible: the original 'Admin' value was itself the bug
        // being fixed, and there's no way to tell these rows apart from
        // users promoted correctly after the fix.
    }
};
