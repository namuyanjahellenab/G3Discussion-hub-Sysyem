<?php

use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\User;

test('joining a group invalidates the cached member list immediately', function () {
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $existingMember = User::factory()->create();
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $existingMember->UserID, 'Status' => 'active']);

    expect($group->activeMembers())->toHaveCount(1);

    $newMember = User::factory()->create();
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $newMember->UserID, 'Status' => 'active']);

    expect($group->activeMembers())->toHaveCount(2);
});
