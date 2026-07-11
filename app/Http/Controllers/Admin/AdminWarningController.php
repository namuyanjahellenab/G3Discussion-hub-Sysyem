<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warning;
use App\Models\Blacklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminWarningController extends Controller
{
    const WARNING_EXPIRY_DAYS = 90;
    const WARNING_THRESHOLD   = 2;
    const AUTO_BLACKLIST_DAYS = 7;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'UserID' => 'required|exists:user,UserID',
            'Reason' => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($validated) {
            // WarningNo = sequential count of warnings ever issued to this user
            $nextWarningNo = Warning::where('UserID', $validated['UserID'])->count() + 1;

            Warning::create([
                'UserID'     => $validated['UserID'],
                'WarningNo'  => $nextWarningNo,
                'Reason'     => $validated['Reason'],
                'ExpiryDate' => now()->addDays(self::WARNING_EXPIRY_DAYS),
            ]);

            $this->maybeAutoBlacklist($validated['UserID']);
        });

        return redirect()->route('admin.blacklist')
            ->with('success', 'Warning issued successfully.');
    }

    /**
     * If the user has WARNING_THRESHOLD or more unexpired warnings,
     * and isn't already actively blacklisted, auto-blacklist them.
     */
    protected function maybeAutoBlacklist(int $userId): void
    {
        $activeWarningCount = Warning::where('UserID', $userId)
            ->where('ExpiryDate', '>', now())
            ->count();

        if ($activeWarningCount < self::WARNING_THRESHOLD) {
            return;
        }

        $alreadyBlacklisted = Blacklist::where('UserID', $userId)
            ->where('EndDate', '>', now())
            ->exists();

        if ($alreadyBlacklisted) {
            return; // don't stack a second active blacklist
        }

        Blacklist::create([
            'UserID'    => $userId,
            'StartDate' => now(),
            'EndDate'   => now()->addDays(self::AUTO_BLACKLIST_DAYS),
            'Reason'    => 'Auto-blacklisted: reached ' . self::WARNING_THRESHOLD . ' active warnings',
            'Type'      => 'Auto',
            'IssuedBy'  => null, // system-issued, no admin
        ]);
    }
}