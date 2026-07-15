<?php

use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Services\MlGatewayClient;
use Mockery\MockInterface;

function makeExportableTopic(): array
{
    $user = User::factory()->create();
    $group = Group::create(['GroupName' => 'G1', 'Description' => 'x', 'CreatedBy' => $user->UserID]);
    $topic = Topic::create(['Title' => 'Export Me', 'GroupID' => $group->GroupID, 'CreatedBy' => $user->UserID, 'Status' => 'open', 'Category' => 'General']);
    Post::create(['TopicID' => $topic->TopicID, 'UserID' => $user->UserID, 'Content' => 'body']);

    return [$topic, $user];
}

test('exporting a topic returns a PDF immediately when the gateway is reachable', function () {
    $this->mock(MlGatewayClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('exportTopicPdf')->once()->andReturn("%PDF-1.4 fake gateway pdf");
    });

    [$topic, $user] = makeExportableTopic();

    $response = $this->actingAs($user)->get(route('topics.export', $topic));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
    expect($response->getContent())->toBe('%PDF-1.4 fake gateway pdf');
});

test('exporting a topic falls back to local Dompdf rendering when the gateway is unreachable', function () {
    $this->mock(MlGatewayClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('exportTopicPdf')->once()->andReturn(null);
    });

    [$topic, $user] = makeExportableTopic();

    $response = $this->actingAs($user)->get(route('topics.export', $topic));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});
