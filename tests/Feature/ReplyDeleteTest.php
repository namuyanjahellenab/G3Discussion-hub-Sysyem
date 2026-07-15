<?php

use App\Models\Group;
use App\Models\Post;
use App\Models\Reply;
use App\Models\Topic;
use App\Models\User;

function makeReplyFixture(): array
{
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $topic = Topic::create(['Title' => 'T1', 'GroupID' => $group->GroupID, 'CreatedBy' => $lecturer->UserID, 'Status' => 'open', 'Category' => 'General']);
    $author = User::factory()->create();
    $post = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $author->UserID, 'Content' => 'q']);
    $reply = Reply::create(['PostID' => $post->PostID, 'UserID' => $author->UserID, 'ReplyContent' => 'a']);

    return [$reply, $author, $lecturer];
}

test('a reply author can delete their own reply', function () {
    [$reply, $author] = makeReplyFixture();

    $response = $this->actingAs($author)->delete(route('replies.destroy', $reply->ReplyID));

    $response->assertRedirect();
    expect(Reply::find($reply->ReplyID))->toBeNull();
});

test('a lecturer can delete any reply', function () {
    [$reply, $author, $lecturer] = makeReplyFixture();

    $response = $this->actingAs($lecturer)->delete(route('replies.destroy', $reply->ReplyID));

    $response->assertRedirect();
    expect(Reply::find($reply->ReplyID))->toBeNull();
});

test('an unrelated student cannot delete someone else\'s reply', function () {
    [$reply] = makeReplyFixture();
    $stranger = User::factory()->create();

    $response = $this->actingAs($stranger)->delete(route('replies.destroy', $reply->ReplyID));

    $response->assertForbidden();
    expect(Reply::find($reply->ReplyID))->not->toBeNull();
});
