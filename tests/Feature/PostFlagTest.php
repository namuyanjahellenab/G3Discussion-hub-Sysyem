<?php

use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;

function makeGroupWithTopic(): array
{
    $creator = User::factory()->create();
    $group = Group::create(['GroupName' => 'Test Group', 'Description' => 'x', 'CreatedBy' => $creator->UserID]);
    $topic = Topic::create(['Title' => 'Test Topic', 'GroupID' => $group->GroupID, 'CreatedBy' => $creator->UserID, 'Status' => 'open', 'Category' => 'General']);

    return [$group, $topic];
}

test('a single flag does not escalate IsFlagged below the threshold', function () {
    [$group, $topic] = makeGroupWithTopic();
    $author = User::factory()->create();
    $flagger = User::factory()->create();
    $post = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $author->UserID, 'Content' => 'hello']);

    $this->actingAs($flagger)->post(route('posts.flag', $post), ['Reason' => 'spam']);

    expect($post->refresh()->IsFlagged)->toBeFalse();
    expect($post->flags()->count())->toBe(1);
});

test('reaching the configured threshold escalates IsFlagged', function () {
    config(['moderation.flag_escalation_threshold' => 2]);
    [$group, $topic] = makeGroupWithTopic();
    $author = User::factory()->create();
    $post = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $author->UserID, 'Content' => 'hello']);

    $this->actingAs(User::factory()->create())->post(route('posts.flag', $post), ['Reason' => 'spam']);
    $this->actingAs(User::factory()->create())->post(route('posts.flag', $post), ['Reason' => 'spam']);

    expect($post->refresh()->IsFlagged)->toBeTrue();
});

test('the same user flagging twice only counts once', function () {
    [$group, $topic] = makeGroupWithTopic();
    $author = User::factory()->create();
    $flagger = User::factory()->create();
    $post = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $author->UserID, 'Content' => 'hello']);

    $this->actingAs($flagger)->post(route('posts.flag', $post), ['Reason' => 'spam']);
    $this->actingAs($flagger)->post(route('posts.flag', $post), ['Reason' => 'spam again']);

    expect($post->flags()->count())->toBe(1);
});

test('admin dismiss clears flags so the post does not immediately re-escalate', function () {
    config(['moderation.flag_escalation_threshold' => 2]);
    [$group, $topic] = makeGroupWithTopic();
    $author = User::factory()->create();
    $post = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $author->UserID, 'Content' => 'hello']);

    $this->actingAs(User::factory()->create())->post(route('posts.flag', $post));
    $this->actingAs(User::factory()->create())->post(route('posts.flag', $post));
    expect($post->refresh()->IsFlagged)->toBeTrue();

    $admin = User::factory()->create(['Role' => 'Administrator']);
    $this->actingAs($admin)->post(route('admin.flagged-content.dismiss', $post->PostID));

    $post->refresh();
    expect($post->IsFlagged)->toBeFalse();
    expect($post->flags()->count())->toBe(0);
});
