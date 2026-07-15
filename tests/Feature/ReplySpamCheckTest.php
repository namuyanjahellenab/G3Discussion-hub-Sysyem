<?php

use App\Models\Group;
use App\Models\Post;
use App\Models\Reply;
use App\Models\Topic;
use App\Models\User;
use App\Services\MlGatewayClient;
use Mockery\MockInterface;

test('a spam reply is blocked and never created', function () {
    $this->mock(MlGatewayClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('moderateContent')->once()->andReturn(['isSpam' => true, 'isEducational' => true]);
    });

    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $asker = User::factory()->create();
    $topic = Topic::create(['Title' => 'T1', 'GroupID' => $group->GroupID, 'CreatedBy' => $asker->UserID, 'Status' => 'open', 'Category' => 'General']);
    $question = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $asker->UserID, 'Content' => 'q']);

    $spammer = User::factory()->create();
    $response = $this->actingAs($spammer)->post(route('posts.reply', $question), [
        'ReplyContent' => 'buy crypto now click here free money',
    ]);

    $response->assertSessionHasErrors('ReplyContent');
    expect(session('errors')->first('ReplyContent'))->toContain('spam');
    expect(Reply::where('PostID', $question->PostID)->count())->toBe(0);
});

test('a reply judged irrelevant to the thread is blocked and never created', function () {
    $this->mock(MlGatewayClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('moderateContent')->once()->andReturn(['isSpam' => false, 'isEducational' => false]);
    });

    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $asker = User::factory()->create();
    $topic = Topic::create(['Title' => 'T1', 'GroupID' => $group->GroupID, 'CreatedBy' => $asker->UserID, 'Status' => 'open', 'Category' => 'General']);
    $question = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $asker->UserID, 'Content' => 'q']);

    $offTopicUser = User::factory()->create();
    $response = $this->actingAs($offTopicUser)->post(route('posts.reply', $question), [
        'ReplyContent' => 'lol nice weather today, anyone up for pizza later',
    ]);

    $response->assertSessionHasErrors('ReplyContent');
    expect(session('errors')->first('ReplyContent'))->toContain('relevant');
    expect(Reply::where('PostID', $question->PostID)->count())->toBe(0);
});

test('a normal reply is created', function () {
    $this->mock(MlGatewayClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('moderateContent')->once()->andReturn(['isSpam' => false, 'isEducational' => true]);
    });

    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $asker = User::factory()->create();
    $topic = Topic::create(['Title' => 'T1', 'GroupID' => $group->GroupID, 'CreatedBy' => $asker->UserID, 'Status' => 'open', 'Category' => 'General']);
    $question = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $asker->UserID, 'Content' => 'q']);

    $answerer = User::factory()->create();
    $response = $this->actingAs($answerer)->post(route('posts.reply', $question), [
        'ReplyContent' => 'Here is a genuine, helpful answer to your question.',
    ]);

    $response->assertRedirect(route('topics.show', $topic->TopicID));

    $reply = Reply::where('PostID', $question->PostID)->first();
    expect($reply)->not->toBeNull();
    expect($reply->IsFlagged)->toBeFalse();
});

test('the reply is checked against the topic and question it is replying to', function () {
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $asker = User::factory()->create();
    $topic = Topic::create(['Title' => 'Sorting Algorithms Help', 'GroupID' => $group->GroupID, 'CreatedBy' => $asker->UserID, 'Status' => 'open', 'Category' => 'General']);
    $question = Post::create(['TopicID' => $topic->TopicID, 'UserID' => $asker->UserID, 'Content' => 'How does merge sort work?']);

    $this->mock(MlGatewayClient::class, function (MockInterface $mock) use ($topic, $question) {
        $mock->shouldReceive('moderateContent')
            ->once()
            ->with('It uses divide and conquer.', "{$topic->Title}\n{$question->Content}")
            ->andReturn(['isSpam' => false, 'isEducational' => true]);
    });

    $answerer = User::factory()->create();
    $this->actingAs($answerer)->post(route('posts.reply', $question), [
        'ReplyContent' => 'It uses divide and conquer.',
    ]);

    expect(Reply::where('PostID', $question->PostID)->count())->toBe(1);
});
