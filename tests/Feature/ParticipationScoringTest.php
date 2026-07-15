<?php

use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Participation;
use App\Models\ParticipationCriteria;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;

function makeGroupWithMember(): array
{
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $topic = Topic::create(['Title' => 'T1', 'GroupID' => $group->GroupID, 'CreatedBy' => $lecturer->UserID, 'Status' => 'open', 'Category' => 'General']);
    $student = User::factory()->create();
    GroupStudent::create(['GroupID' => $group->GroupID, 'UserID' => $student->UserID, 'Status' => 'active']);

    return [$group, $topic, $student];
}

test('replying to a question earns participation credit (previously only chat posts did)', function () {
    [$group, $topic, $student] = makeGroupWithMember();
    $mainPost = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $student->UserID, 'Content' => 'question']);

    $this->actingAs($student)->post(route('posts.reply', $mainPost), ['ReplyContent' => 'my answer']);

    $participation = Participation::where('UserID', $student->UserID)->where('GroupID', $group->GroupID)->first();
    expect($participation)->not->toBeNull();
    expect($participation->ReplyCount)->toBe(1);
});

test('participation is scoped per group, not global', function () {
    [$groupA, $topicA, $student] = makeGroupWithMember();
    $lecturerB = User::factory()->create(['Role' => 'Lecturer']);
    $groupB = Group::create(['GroupName' => 'G2', 'Description' => 'x', 'CreatedBy' => $lecturerB->UserID]);
    $topicB = Topic::create(['Title' => 'T2', 'GroupID' => $groupB->GroupID, 'CreatedBy' => $lecturerB->UserID, 'Status' => 'open', 'Category' => 'General']);
    GroupStudent::create(['GroupID' => $groupB->GroupID, 'UserID' => $student->UserID, 'Status' => 'active']);

    $postA = Post::create(['TopicID' => $topicA->TopicID, 'UserID' => $student->UserID, 'Content' => 'q']);
    $this->actingAs($student)->post(route('posts.reply', $postA), ['ReplyContent' => 'answer in group A']);

    $scoreA = Participation::where('UserID', $student->UserID)->where('GroupID', $groupA->GroupID)->first();
    $scoreB = Participation::where('UserID', $student->UserID)->where('GroupID', $groupB->GroupID)->first();

    expect($scoreA->ReplyCount)->toBe(1);
    expect($scoreB)->toBeNull(); // no activity in group B yet
});

test('ParticipationCriteria is configurable per group instead of hardcoded', function () {
    [$group, $topic, $student] = makeGroupWithMember();
    ParticipationCriteria::create(['GroupID' => $group->GroupID, 'PointsPerPost' => 5, 'PointsPerReply' => 3, 'PointsPerAcceptedAnswer' => 0]);

    $mainPost = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $student->UserID, 'Content' => 'question']);
    $this->actingAs($student)->post(route('posts.reply', $mainPost), ['ReplyContent' => 'my answer']);

    $participation = Participation::where('UserID', $student->UserID)->where('GroupID', $group->GroupID)->first();
    // 1 post (the question itself) * 5 + 1 reply * 3 = 8, using this group's
    // configured criteria rather than the old hardcoded PostCount*2+ReplyCount*1.
    expect((float) $participation->ParticipationScore)->toBe(8.0);
});

test('a flagged reply is excluded from participation once escalated', function () {
    config(['moderation.flag_escalation_threshold' => 2]);
    [$group, $topic, $student] = makeGroupWithMember();
    $mainPost = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $student->UserID, 'Content' => 'question']);
    $this->actingAs($student)->post(route('posts.reply', $mainPost), ['ReplyContent' => 'spammy answer']);

    $reply = \App\Models\Reply::where('UserID', $student->UserID)->first();

    expect(Participation::where('UserID', $student->UserID)->where('GroupID', $group->GroupID)->first()->ReplyCount)->toBe(1);

    $this->actingAs(User::factory()->create())->post(route('replies.flag', $reply));
    $this->actingAs(User::factory()->create())->post(route('replies.flag', $reply));

    expect($reply->refresh()->IsFlagged)->toBeTrue();
    expect(Participation::where('UserID', $student->UserID)->where('GroupID', $group->GroupID)->first()->ReplyCount)->toBe(0);
});
