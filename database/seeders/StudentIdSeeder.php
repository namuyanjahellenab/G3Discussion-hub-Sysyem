<?php

namespace Database\Seeders;

use App\Models\StudentId;
use App\Models\User;
use Illuminate\Database\Seeder;

// Seeds the StudentIDs whitelist (see AuthController::register()/apiRegister())
// with real, git-shared data instead of a manually-run tinker one-off, so
// every team member gets the same setup on `php artisan db:seed`.
//
// Two things happen, both driven by whatever's actually in THIS database
// when the seeder runs (not hardcoded to specific people) - so it behaves
// correctly regardless of whose machine it's run on:
//
//   1. Every existing Student account that predates this whitelist gets a
//      code retroactively attached (marked used, linked to that account) -
//      otherwise those accounts would show up nowhere in the admin's
//      Student IDs list even though they're real, already-registered
//      students.
//   2. A handful of spare, unused codes are topped up so there's always
//      something available for a genuinely new registration - re-running
//      this seeder tops back up to the target spare count instead of
//      piling on more each time.
class StudentIdSeeder extends Seeder
{
    private const SPARE_CODE_COUNT = 5;
    private const CODE_PREFIX = 'A-';

    public function run(): void
    {
        $nextNumber = $this->nextCodeNumber();

        $linkedUserIds = StudentId::whereNotNull('LinkedUserID')->pluck('LinkedUserID');
        $studentsWithoutCode = User::where('Role', 'Student')
            ->whereNotIn('UserID', $linkedUserIds)
            ->orderBy('UserID')
            ->get();

        foreach ($studentsWithoutCode as $student) {
            StudentId::create([
                'StudentIDNumber' => self::CODE_PREFIX . $nextNumber++,
                'IsUsed' => true,
                'LinkedUserID' => $student->UserID,
            ]);
        }

        $currentlyUnused = StudentId::where('IsUsed', false)->count();
        $spareToCreate = max(0, self::SPARE_CODE_COUNT - $currentlyUnused);

        for ($i = 0; $i < $spareToCreate; $i++) {
            StudentId::create([
                'StudentIDNumber' => self::CODE_PREFIX . $nextNumber++,
                'IsUsed' => false,
            ]);
        }

        $this->command?->info(
            "StudentIdSeeder: attached codes to {$studentsWithoutCode->count()} existing student(s), "
            . "topped up {$spareToCreate} spare code(s)."
        );
    }

    /** Continues the "A-1", "A-2", ... sequence from whatever's already in
     *  the table, instead of restarting at A-1 and colliding with codes a
     *  previous run (or a teammate's differently-seeded database) already
     *  created. */
    private function nextCodeNumber(): int
    {
        $highest = StudentId::query()
            ->pluck('StudentIDNumber')
            ->map(function ($code) {
                return preg_match('/^' . preg_quote(self::CODE_PREFIX, '/') . '(\d+)$/', $code, $m) ? (int) $m[1] : 0;
            })
            ->max();

        return ($highest ?? 0) + 1;
    }
}
