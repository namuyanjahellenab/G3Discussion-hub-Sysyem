<?php

use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Topic;
use App\Models\TopicExclusion;
use App\Models\User;

test('excluded users do not see the topic in the forum listing', function () {
    $creator = User::factory()->create();
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $creator->UserID]);
    $excludedUser = User::factory()->create();
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $excludedUser->UserID, 'Status' => 'active']);

    $topic = Topic::create(['Title' => 'Secret Topic', 'GroupID' => $group->GroupID, 'CreatedBy' => $creator->UserID, 'Status' => 'open', 'Category' => 'General']);
    TopicExclusion::create(['TopicID' => $topic->TopicID, 'UserID' => $excludedUser->UserID]);

    $response = $this->actingAs($excludedUser)->get(route('groups.topics', $group));

    $response->assertOk();
    $response->assertDontSee('Secret Topic');
});

test('excluded users get a 403 on direct access to the topic', function () {
    $creator = User::factory()->create();
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $creator->UserID]);
    $excludedUser = User::factory()->create();

    $topic = Topic::create(['Title' => 'Secret Topic', 'GroupID' => $group->GroupID, 'CreatedBy' => $creator->UserID, 'Status' => 'open', 'Category' => 'General']);
    TopicExclusion::create(['TopicID' => $topic->TopicID, 'UserID' => $excludedUser->UserID]);

    $response = $this->actingAs($excludedUser)->get(route('topics.show', $topic));

    $response->assertForbidden();
});

test('a non-excluded member can see and open the topic normally', function () {
    $creator = User::factory()->create();
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $creator->UserID]);
    $member = User::factory()->create();
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $member->UserID, 'Status' => 'active']);

    $topic = Topic::create(['Title' => 'Visible Topic', 'GroupID' => $group->GroupID, 'CreatedBy' => $creator->UserID, 'Status' => 'open', 'Category' => 'General']);

    $listResponse = $this->actingAs($member)->get(route('groups.topics', $group));
    $listResponse->assertOk();
    $listResponse->assertSee('Visible Topic');

    $showResponse = $this->actingAs($member)->get(route('topics.show', $topic));
    $showResponse->assertOk();
});

test('creating a topic with a custom audience persists exclusions', function () {
    $creator = User::factory()->create();
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $creator->UserID]);
    $excluded = User::factory()->create();
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $excluded->UserID, 'Status' => 'active']);

    $response = $this->actingAs($creator)->post(route('topics.store'), [
        'Title' => 'Custom Audience Topic',
        'GroupID' => $group->GroupID,
        'Content' => 'question body',
        'audience' => 'custom',
        'exclude' => [$excluded->UserID],
    ]);

    $topic = Topic::where('Title', 'Custom Audience Topic')->first();
    $response->assertRedirect(route('topics.show', $topic));
    expect(TopicExclusion::where('TopicID', $topic->TopicID)->where('UserID', $excluded->UserID)->exists())->toBeTrue();
});
