<?php

use App\Models\Group;
use App\Services\GroupBrowseService;
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
        Schema::table('Group', function (Blueprint $table) {
            $table->string('CourseCode', 20)->nullable()->after('Description');
        });

        // Backfill every existing group that doesn't get an explicit code
        // from GroupSeeder (which runs separately and sets the real CSC30x
        // codes) using the same formula GroupBrowseService used to derive
        // one on the fly, so nothing goes from "has a code" to "blank"
        // the moment this column exists.
        $browseService = new GroupBrowseService();
        Group::whereNull('CourseCode')->get()->each(function (Group $group) use ($browseService) {
            $group->update(['CourseCode' => $browseService->deriveGroupCode($group)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Group', function (Blueprint $table) {
            $table->dropColumn('CourseCode');
        });
    }
};
