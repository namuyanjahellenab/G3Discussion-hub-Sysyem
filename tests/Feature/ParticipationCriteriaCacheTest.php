<?php

use App\Models\Group;
use App\Models\ParticipationCriteria;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('forGroup caches the criteria so repeated calls do not re-hit the database', function () {
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    ParticipationCriteria::create(['GroupID' => $group->GroupID, 'PointsPerPost' => 4, 'PointsPerReply' => 2, 'PointsPerAcceptedAnswer' => 1]);

    ParticipationCriteria::forGroup($group->GroupID); // warm the cache

    DB::enableQueryLog();
    $criteria = ParticipationCriteria::forGroup($group->GroupID);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($criteria->PointsPerPost)->toBe(4);
    expect($queries)->toBeEmpty();
});

test('updating criteria busts the cache immediately', function () {
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $criteria = ParticipationCriteria::create(['GroupID' => $group->GroupID, 'PointsPerPost' => 4, 'PointsPerReply' => 2, 'PointsPerAcceptedAnswer' => 1]);

    expect(ParticipationCriteria::forGroup($group->GroupID)->PointsPerPost)->toBe(4);

    $criteria->update(['PointsPerPost' => 9]);

    expect(ParticipationCriteria::forGroup($group->GroupID)->PointsPerPost)->toBe(9);
});
