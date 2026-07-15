<?php

use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Participation;
use App\Models\Post;
use App\Models\Reply;
use App\Models\Topic;
use App\Models\User;

function makeGroupTopicAndReply(): array
{
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $topic = Topic::create(['Title' => 'T1', 'GroupID' => $group->GroupID, 'CreatedBy' => $lecturer->UserID, 'Status' => 'open', 'Category' => 'General']);
    $author = User::factory()->create();
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $author->UserID, 'Status' => 'active']);
    $post = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $author->UserID, 'Content' => 'question']);
    $reply = Reply::create(['PostID' => $post->PostID, 'UserID' => $author->UserID, 'ReplyContent' => 'answer']);

    return [$group, $post, $reply, $author];
}

test('admin dismissing a flagged reply clears its flags', function () {
    config(['moderation.flag_escalation_threshold' => 1]);
    [$group, $post, $reply] = makeGroupTopicAndReply();

    $this->actingAs(User::factory()->create())->post(route('replies.flag', $reply));
    expect($reply->refresh()->IsFlagged)->toBeTrue();

    $admin = User::factory()->create(['Role' => 'Administrator']);
    $this->actingAs($admin)->post(route('admin.flagged-content.replies.dismiss', $reply->ReplyID));

    $reply->refresh();
    expect($reply->IsFlagged)->toBeFalse();
    expect($reply->flags()->count())->toBe(0);
});

test('admin deleting a reply backs its participation points out immediately', function () {
    [$group, $post, $reply, $author] = makeGroupTopicAndReply();

    // storeReply() isn't what created this reply (it was seeded directly),
    // so recalc it once to establish the pre-delete baseline.
    app(\App\Services\ParticipationService::class)->recalculate($author->UserID, $group->GroupID);
    expect(Participation::where('UserID', $author->UserID)->where('GroupID', $group->GroupID)->first()->ReplyCount)->toBe(1);

    $admin = User::factory()->create(['Role' => 'Administrator']);
    $this->actingAs($admin)->delete(route('admin.flagged-content.replies.destroy', $reply->ReplyID));

    expect(Reply::find($reply->ReplyID))->toBeNull();
    expect(Participation::where('UserID', $author->UserID)->where('GroupID', $group->GroupID)->first()->ReplyCount)->toBe(0);
});
