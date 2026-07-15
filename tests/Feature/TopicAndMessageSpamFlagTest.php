<?php

use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Services\MlGatewayClient;
use Mockery\MockInterface;

test('a topic flagged as spam is still created, but auto-flagged for moderation', function () {
    $this->mock(MlGatewayClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('moderateContent')->once()->andReturn(['isSpam' => true, 'isEducational' => true]);
    });

    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $poster = User::factory()->create();

    $response = $this->actingAs($poster)->post(route('topics.store'), [
        'Title' => 'Great deals inside',
        'GroupID' => $group->GroupID,
        'Content' => 'buy crypto now click here free money',
    ]);

    $response->assertSessionDoesntHaveErrors();

    $topic = Topic::where('GroupID', $group->GroupID)->latest('CreatedAt')->first();
    expect($topic)->not->toBeNull();

    $post = Post::where('TopicID', $topic->TopicID)->first();
    expect($post)->not->toBeNull();
    expect($post->IsFlagged)->toBeTrue();
    expect($post->FlaggedReason)->not->toBeNull();

    $response->assertRedirect(route('topics.show', $topic->TopicID));
});

test('a topic judged not educational is blocked entirely', function () {
    $this->mock(MlGatewayClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('moderateContent')->once()->andReturn(['isSpam' => false, 'isEducational' => false]);
    });

    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $poster = User::factory()->create();

    $response = $this->actingAs($poster)->post(route('topics.store'), [
        'Title' => 'Pizza tonight?',
        'GroupID' => $group->GroupID,
        'Content' => 'anyone free for pizza and a movie later, nothing to do with class',
    ]);

    $response->assertSessionHasErrors('Content');
    expect(Topic::where('GroupID', $group->GroupID)->count())->toBe(0);
});

test('a message flagged as spam is still created, but auto-flagged for moderation', function () {
    $this->mock(MlGatewayClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('moderateContent')->once()->andReturn(['isSpam' => true, 'isEducational' => true]);
    });

    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $poster = User::factory()->create();
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $poster->UserID, 'Status' => 'active']);
    Topic::create(['Title' => 'General Discussion', 'GroupID' => $group->GroupID, 'CreatedBy' => $lecturer->UserID, 'Status' => 'open', 'Category' => 'General']);

    $response = $this->actingAs($poster)->post(route('messages.store'), [
        'group_id' => $group->GroupID,
        'content' => 'buy crypto now click here free money',
    ]);

    $response->assertSessionDoesntHaveErrors();

    $post = Post::where('UserID', $poster->UserID)->latest('CreatedAt')->first();
    expect($post)->not->toBeNull();
    expect($post->IsFlagged)->toBeTrue();
    expect($post->FlaggedReason)->not->toBeNull();
});

test('a message judged not educational is blocked entirely', function () {
    $this->mock(MlGatewayClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('moderateContent')->once()->andReturn(['isSpam' => false, 'isEducational' => false]);
    });

    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $poster = User::factory()->create();
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $poster->UserID, 'Status' => 'active']);
    Topic::create(['Title' => 'General Discussion', 'GroupID' => $group->GroupID, 'CreatedBy' => $lecturer->UserID, 'Status' => 'open', 'Category' => 'General']);

    $countBefore = Post::where('UserID', $poster->UserID)->count();

    $response = $this->actingAs($poster)->post(route('messages.store'), [
        'group_id' => $group->GroupID,
        'content' => 'lol nice weather today, anyone up for pizza later',
    ]);

    $response->assertSessionHasErrors('content');
    expect(Post::where('UserID', $poster->UserID)->count())->toBe($countBefore);
});
