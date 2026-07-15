<?php

use App\Jobs\ClassifyTopicJob;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('visiting the forum page dispatches classification jobs instead of blocking on the ML gateway', function () {
    Queue::fake();

    $user = User::factory()->create();
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $user->UserID]);
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $user->UserID, 'Status' => 'active']);
    $topic = Topic::create(['Title' => 'Unclassified Topic', 'GroupID' => $group->GroupID, 'CreatedBy' => $user->UserID, 'Status' => 'open', 'Category' => 'General']);

    $response = $this->actingAs($user)->get(route('forum.index'));

    $response->assertOk();
    Queue::assertPushed(ClassifyTopicJob::class, fn ($job) => $job->topicId === $topic->TopicID);
});

test('a topic already classified is not re-dispatched', function () {
    Queue::fake();

    $user = User::factory()->create();
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $user->UserID]);
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $user->UserID, 'Status' => 'active']);
    $topic = Topic::create(['Title' => 'Already Classified', 'GroupID' => $group->GroupID, 'CreatedBy' => $user->UserID, 'Status' => 'open', 'Category' => 'General']);
    \App\Models\TopicClassification::create(['TopicID' => $topic->TopicID, 'PredictedCategory' => 'General', 'ConfidenceScore' => 0.9]);

    $this->actingAs($user)->get(route('forum.index'));

    Queue::assertNotPushed(ClassifyTopicJob::class);
});
