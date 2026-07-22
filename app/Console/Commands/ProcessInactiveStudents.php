<?php

namespace App\Console\Commands;

use App\Models\Blacklist;
use App\Models\BlacklistSetting;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserInactivityWarning;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessInactiveStudents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:process-inactivity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recompute student Active/Inactive status and run the auto warning/blacklist pipeline for inactivity.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Runs before the per-student sweep below: if a blacklist expired
        // since the last run, this needs to reset Status back to 'Active'
        // (and fire the "restriction lifted" notice) before processStudent()
        // gets a chance to reclassify that same user as Inactive within this
        // very run - otherwise their Status would never be seen as
        // 'Blacklisted' by this method and the notice would never fire.
        $this->syncExpiredBlacklists();

        $settings = BlacklistSetting::current();
        $cutoff = now()->subSeconds(BlacklistSetting::toSeconds($settings->InactivityValue, $settings->InactivityUnit));

        User::where('Role', 'Student')->chunkById(200, function ($students) use ($settings, $cutoff) {
            foreach ($students as $student) {
                $this->processStudent($student, $settings, $cutoff);
            }
        });

        return self::SUCCESS;
    }

    /**
     * A student who currently has an active blacklist entry is excluded from
     * inactivity tracking entirely, per spec - they're already accounted for
     * as 'Blacklisted', not 'Inactive'.
     */
    private function processStudent(User $student, BlacklistSetting $settings, Carbon $cutoff): void
    {
        $activelyBlacklisted = Blacklist::where('UserID', $student->UserID)->active()->exists();

        if ($activelyBlacklisted) {
            if ($student->Status !== 'Blacklisted') {
                $student->update(['Status' => 'Blacklisted']);
            }

            return;
        }

        $lastActive = $student->LastActive ? Carbon::parse($student->LastActive) : null;
        $isInactive = !$lastActive || $lastActive->lte($cutoff);

        if ($isInactive) {
            if ($student->Status !== 'Inactive') {
                $student->update(['Status' => 'Inactive']);
            }

            if ($settings->AutoBlacklistEnabled) {
                $this->processWarningPipeline($student, $settings, $lastActive ?? Carbon::parse($student->CreatedAt));
            }

            return;
        }

        if ($student->Status !== 'Active') {
            $student->update(['Status' => 'Active']);
        }
    }

    private function processWarningPipeline(User $student, BlacklistSetting $settings, Carbon $inactiveSince): void
    {
        $tracking = UserInactivityWarning::firstOrCreate(['UserID' => $student->UserID]);

        $firstThreshold = BlacklistSetting::toSeconds($settings->FirstWarningValue, $settings->FirstWarningUnit);
        $secondThreshold = BlacklistSetting::toSeconds($settings->SecondWarningValue, $settings->SecondWarningUnit);
        $blacklistThreshold = BlacklistSetting::toSeconds($settings->BlacklistAfterSecondWarningValue, $settings->BlacklistAfterSecondWarningUnit);

        if (!$tracking->FirstWarningAt) {
            if (now()->timestamp - $inactiveSince->timestamp >= $firstThreshold) {
                Notification::create([
                    'UserID' => $student->UserID,
                    'Message' => "This is your first warning. You have been inactive for {$settings->FirstWarningValue} " . \Illuminate\Support\Str::plural(rtrim($settings->FirstWarningUnit, 's'), $settings->FirstWarningValue),
                    'Status' => false,
                    'Type' => 'Warning',
                ]);

                $tracking->update(['FirstWarningAt' => now()]);
            }

            return;
        }

        if (!$tracking->SecondWarningAt) {
            if (now()->timestamp - $tracking->FirstWarningAt->timestamp >= $secondThreshold) {
                Notification::create([
                    'UserID' => $student->UserID,
                    'Message' => 'This is your second warning. You have been inactive for ' . BlacklistSetting::formatDuration($firstThreshold + $secondThreshold),
                    'Status' => false,
                    'Type' => 'Warning',
                ]);

                $tracking->update(['SecondWarningAt' => now()]);
            }

            return;
        }

        if (now()->timestamp - $tracking->SecondWarningAt->timestamp >= $blacklistThreshold) {
            $endDate = now()->addSeconds(BlacklistSetting::toSeconds($settings->BlacklistDurationValue, $settings->BlacklistDurationUnit));

            Blacklist::create([
                'UserID' => $student->UserID,
                'StartDate' => now(),
                'EndDate' => $endDate,
                'Reason' => 'Automatically blacklisted for inactivity',
                'Type' => 'Auto',
                'IssuedBy' => null,
            ]);

            $student->update(['Status' => 'Blacklisted']);

            Notification::create([
                'UserID' => $student->UserID,
                'Message' => 'You have been blacklisted for not responding to the second warning of being inactive',
                'Status' => false,
                'Type' => 'Blacklist',
            ]);

            $tracking->delete();
        }
    }

    /**
     * A blacklist's EndDate lapsing today only syncs User.Status back to
     * Active via the admin's manual "Dismiss" action - this sweep applies
     * that same reset automatically once EndDate passes on its own, which
     * both the inactivity feature's own auto-blacklists and pre-existing
     * manual/warning-triggered ones rely on to stop being "Blacklisted"
     * forever once expired.
     */
    private function syncExpiredBlacklists(): void
    {
        $blacklistedUserIds = User::where('Status', 'Blacklisted')->pluck('UserID');

        if ($blacklistedUserIds->isEmpty()) {
            return;
        }

        $stillActiveIds = Blacklist::whereIn('UserID', $blacklistedUserIds)->active()->pluck('UserID')->unique();
        $toReset = $blacklistedUserIds->diff($stillActiveIds);

        if ($toReset->isEmpty()) {
            return;
        }

        User::whereIn('UserID', $toReset)->update(['Status' => 'Active']);

        foreach ($toReset as $userId) {
            Notification::create([
                'UserID' => $userId,
                'Message' => 'Your account restriction has been lifted. You can now post and send messages again.',
                'Status' => false,
                'Type' => 'Blacklist',
            ]);
        }
    }
}
