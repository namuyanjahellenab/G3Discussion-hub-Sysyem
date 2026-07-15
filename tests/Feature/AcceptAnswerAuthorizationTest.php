<?php

use App\Models\Group;
use App\Models\Post;
use App\Models\Reply;
use App\Models\Topic;
use App\Models\User;

function makeQuestionAndAnswer(): array
{
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $asker = User::factory()->create();
    $answerer = User::factory()->create();
    $topic = Topic::create(['Title' => 'T1', 'GroupID' => $group->GroupID, 'CreatedBy' => $asker->UserID, 'Status' => 'open', 'Category' => 'General']);
    $question = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $asker->UserID, 'Content' => 'q']);
    $reply = Reply::create(['PostID' => $question->PostID, 'UserID' => $answerer->UserID, 'ReplyContent' => 'a']);

    return [$reply, $asker, $answerer, $lecturer];
}

test('the person who asked the question can mark a reply as the accepted answer', function () {
    [$reply, $asker] = makeQuestionAndAnswer();

    $response = $this->actingAs($asker)->post(route('replies.accept', $reply));

    $response->assertRedirect();
    expect($reply->refresh()->IsAccepted)->toBeTrue();
});

test('a lecturer can still mark a reply as the accepted answer', function () {
    [$reply, , , $lecturer] = makeQuestionAndAnswer();

    $response = $this->actingAs($lecturer)->post(route('replies.accept', $reply));

    $response->assertRedirect();
    expect($reply->refresh()->IsAccepted)->toBeTrue();
});

test('an unrelated student cannot mark someone else\'s question as answered', function () {
    [$reply] = makeQuestionAndAnswer();
    $stranger = User::factory()->create();

    $response = $this->actingAs($stranger)->post(route('replies.accept', $reply));

    $response->assertForbidden();
    expect($reply->refresh()->IsAccepted)->toBeFalse();
});
