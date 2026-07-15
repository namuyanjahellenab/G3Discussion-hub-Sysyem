<?php

use App\Jobs\ExportTopicPdfJob;
use App\Models\Group;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Topic;
use App\Models\TopicExport;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

function makeExportableTopic(): array
{
    $user = User::factory()->create();
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $user->UserID]);
    $topic = Topic::create(['Title' => 'Export Me', 'GroupID' => $group->GroupID, 'CreatedBy' => $user->UserID, 'Status' => 'open', 'Category' => 'General']);
    Post::create(['TopicID' => $topic->TopicID, 'UserID' => $user->UserID, 'Content' => 'body']);

    return [$topic, $user];
}

test('requesting an export creates a pending record and dispatches the job instead of blocking the request', function () {
    Queue::fake();
    [$topic, $user] = makeExportableTopic();

    $response = $this->actingAs($user)->get(route('topics.export', $topic));

    $response->assertRedirect();
    $response->assertSessionHas('status');

    $export = TopicExport::where('TopicID', $topic->TopicID)->first();
    expect($export)->not->toBeNull();
    expect($export->Status)->toBe('pending');

    Queue::assertPushed(ExportTopicPdfJob::class, fn ($job) => $job->topicExportId === $export->TopicExportID);
});

test('running the export job marks it ready and notifies the user with a download link', function () {
    [$topic, $user] = makeExportableTopic();
    $export = TopicExport::create(['TopicID' => $topic->TopicID, 'UserID' => $user->UserID, 'Status' => 'pending']);

    (new ExportTopicPdfJob($export->TopicExportID))->handle(app(\App\Services\MlGatewayClient::class));

    $export->refresh();
    expect($export->Status)->toBe('ready');
    expect($export->FilePath)->not->toBeNull();

    $notification = Notification::where('UserID', $user->UserID)->where('Type', 'Export')->first();
    expect($notification)->not->toBeNull();
    expect($notification->Message)->toContain('Download it here');
});

test('the owner can download a ready export', function () {
    [$topic, $user] = makeExportableTopic();
    $export = TopicExport::create(['TopicID' => $topic->TopicID, 'UserID' => $user->UserID, 'Status' => 'pending']);
    (new ExportTopicPdfJob($export->TopicExportID))->handle(app(\App\Services\MlGatewayClient::class));

    $response = $this->actingAs($user)->get(route('topic-exports.download', $export->fresh()->TopicExportID));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf');
});

test('a different user cannot download someone else\'s export', function () {
    [$topic, $user] = makeExportableTopic();
    $stranger = User::factory()->create();
    $export = TopicExport::create(['TopicID' => $topic->TopicID, 'UserID' => $user->UserID, 'Status' => 'pending']);
    (new ExportTopicPdfJob($export->TopicExportID))->handle(app(\App\Services\MlGatewayClient::class));

    $response = $this->actingAs($stranger)->get(route('topic-exports.download', $export->fresh()->TopicExportID));

    $response->assertForbidden();
});

test('downloading a still-pending export shows a friendly message instead of an error', function () {
    [$topic, $user] = makeExportableTopic();
    $export = TopicExport::create(['TopicID' => $topic->TopicID, 'UserID' => $user->UserID, 'Status' => 'pending']);

    $response = $this->actingAs($user)->get(route('topic-exports.download', $export->TopicExportID));

    $response->assertRedirect();
    $response->assertSessionHas('status');
});
