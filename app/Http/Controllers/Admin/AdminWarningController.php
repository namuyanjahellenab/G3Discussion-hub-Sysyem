<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warning;
use App\Models\Blacklist;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminWarningController extends Controller
{
    const WARNING_EXPIRY_DAYS = 1;
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
            $expiryDate = now()->addDays(self::WARNING_EXPIRY_DAYS);

            Warning::create([
                'UserID'     => $validated['UserID'],
                'WarningNo'  => $nextWarningNo,
                'Reason'     => $validated['Reason'],
                'ExpiryDate' => $expiryDate,
            ]);

            // Warnings carry no IssuedBy column (anonymous by design), so the
            // student-facing message never names an admin either.
            Notification::create([
                'UserID'  => $validated['UserID'],
                'Message' => "You've received warning #{$nextWarningNo} from an administrator: \"{$validated['Reason']}\". This warning stays on your record until {$expiryDate->setTimezone('Africa/Kampala')->format('d M Y')}.",
                'Status'  => false,
                'Type'    => 'Warning',
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

        $endDate = now()->addDays(self::AUTO_BLACKLIST_DAYS);

        Blacklist::create([
            'UserID'    => $userId,
            'StartDate' => now(),
            'EndDate'   => $endDate,
            'Reason'    => 'reached ' . self::WARNING_THRESHOLD . ' active warnings',
            'Type'      => 'Auto',
            'IssuedBy'  => null, // system-issued, no admin
        ]);

        // Keep User.Status in sync with the Blacklist table so the login
        // gate (AuthController) and any Status-based checks stay accurate.
        User::where('UserID', $userId)->update(['Status' => 'Blacklisted']);

        Notification::create([
            'UserID'  => $userId,
            'Message' => "Your account has been automatically restricted until {$endDate->setTimezone('Africa/Kampala')->format('d M Y')} for reaching " . self::WARNING_THRESHOLD . ' active warnings. You will not be able to post or send messages until then.',
            'Status'  => false,
            'Type'    => 'Blacklist',
        ]);
    }
}