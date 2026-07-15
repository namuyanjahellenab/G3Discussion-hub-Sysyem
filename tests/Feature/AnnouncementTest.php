<?php

use App\Models\Announcement;
use App\Models\AnnouncementExclusion;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Notification;
use App\Models\User;

test('a lecturer can send an announcement without a scheduled quiz (the old gate is removed)', function () {
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $student = User::factory()->create();
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $student->UserID, 'Status' => 'active']);

    // Note: no Quiz row exists anywhere for this lecturer/group at all.
    $response = $this->actingAs($lecturer)->post(route('announcements.store'), [
        'GroupID' => $group->GroupID,
        'message' => 'Report to the dean\'s office.',
    ]);

    $response->assertRedirect(route('announcements.index'));
    expect(Announcement::where('GroupID', $group->GroupID)->count())->toBe(1);
    expect(Notification::where('UserID', $student->UserID)->where('Type', 'Announcement')->count())->toBe(1);
});

test('excluded members do not get notified and are recorded on the announcement', function () {
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $included = User::factory()->create();
    $excluded = User::factory()->create();
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $included->UserID, 'Status' => 'active']);
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $excluded->UserID, 'Status' => 'active']);

    $this->actingAs($lecturer)->post(route('announcements.store'), [
        'GroupID' => $group->GroupID,
        'message' => 'General notice.',
        'exclude' => [$excluded->UserID],
    ]);

    expect(Notification::where('UserID', $included->UserID)->where('Type', 'Announcement')->exists())->toBeTrue();
    expect(Notification::where('UserID', $excluded->UserID)->where('Type', 'Announcement')->exists())->toBeFalse();

    $announcement = Announcement::where('GroupID', $group->GroupID)->latest('CreatedAt')->first();
    expect(AnnouncementExclusion::where('AnnouncementID', $announcement->AnnouncementID)->where('UserID', $excluded->UserID)->exists())->toBeTrue();
});

test('only an admin can send a campus-wide announcement (null GroupID)', function () {
    // GroupID is a required field for non-admins, so a lecturer can't even
    // submit a campus-wide request — enforced at validation, not just auth.
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);

    $response = $this->actingAs($lecturer)->post(route('announcements.store'), [
        'message' => 'Campus-wide attempt by a lecturer.',
    ]);

    $response->assertSessionHasErrors('GroupID');
    expect(Announcement::whereNull('GroupID')->exists())->toBeFalse();
});

test('an admin can send a campus-wide announcement to every active student', function () {
    $admin = User::factory()->create(['Role' => 'Administrator']);
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $groupA = Group::create(['GroupName' => 'A', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $groupB = Group::create(['GroupName' => 'B', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $studentA = User::factory()->create();
    $studentB = User::factory()->create();
    GroupStudent::create(['GroupID' => $groupA->GroupID, 'UserID' => $studentA->UserID, 'Status' => 'active']);
    GroupStudent::create(['GroupID' => $groupB->GroupID, 'UserID' => $studentB->UserID, 'Status' => 'active']);

    $this->actingAs($admin)->post(route('announcements.store'), ['message' => 'Campus-wide notice.']);

    expect(Notification::where('UserID', $studentA->UserID)->where('Type', 'Announcement')->exists())->toBeTrue();
    expect(Notification::where('UserID', $studentB->UserID)->where('Type', 'Announcement')->exists())->toBeTrue();
    expect(Announcement::whereNull('GroupID')->exists())->toBeTrue();
});

test('a student cannot send an announcement', function () {
    $student = User::factory()->create();
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $student->UserID]);

    $response = $this->actingAs($student)->post(route('announcements.store'), [
        'GroupID' => $group->GroupID,
        'message' => 'Not allowed.',
    ]);

    $response->assertForbidden();
});

test('announcements are persisted and browsable, not just a one-time notification', function () {
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);

    $this->actingAs($lecturer)->post(route('announcements.store'), [
        'GroupID' => $group->GroupID,
        'message' => 'Persisted announcement.',
    ]);

    $response = $this->actingAs($lecturer)->get(route('announcements.index'));

    $response->assertOk();
    $response->assertSee('Persisted announcement.');
});
