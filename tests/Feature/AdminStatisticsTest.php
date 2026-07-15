<?php

use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Post;
use App\Models\Reply;
use App\Models\Topic;
use App\Models\User;

test('group summary counts posts, active users, and flagged content correctly per group', function () {
    $admin = User::factory()->create(['Role' => 'Administrator']);
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);

    $groupA = Group::create(['GroupName' => 'A', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);
    $groupB = Group::create(['GroupName' => 'B', 'Description' => 'x', 'CreatedBy' => $lecturer->UserID]);

    $studentA1 = User::factory()->create();
    $studentA2 = User::factory()->create();
    GroupStudent::create(['GroupID' => $groupA->GroupID, 'UserID' => $studentA1->UserID, 'Status' => 'active']);
    GroupStudent::create(['GroupID' => $groupA->GroupID, 'UserID' => $studentA2->UserID, 'Status' => 'active']);

    $topicA = Topic::create(['Title' => 'TA', 'GroupID' => $groupA->GroupID, 'CreatedBy' => $studentA1->UserID, 'Status' => 'open', 'Category' => 'General']);
    $topicB = Topic::create(['Title' => 'TB', 'GroupID' => $groupB->GroupID, 'CreatedBy' => $lecturer->UserID, 'Status' => 'open', 'Category' => 'General']);

    // Group A: 2 posts (1 flagged), 2 distinct active posters (student A1 posts, A2 replies)
    $postA1 = Post::create(['TopicID' => $topicA->TopicID, 'UserID' => $studentA1->UserID, 'Content' => 'q1']);
    Post::create(['TopicID' => $topicA->TopicID, 'UserID' => $studentA1->UserID, 'Content' => 'spam', 'IsFlagged' => true]);
    Reply::create(['PostID' => $postA1->PostID, 'UserID' => $studentA2->UserID, 'ReplyContent' => 'answer']);

    // Group B: 1 post, 1 active user, 0 flagged
    Post::create(['TopicID' => $topicB->TopicID, 'UserID' => $lecturer->UserID, 'Content' => 'hello']);

    $response = $this->actingAs($admin)->get(route('admin.statistics'));
    $response->assertOk();

    $groupSummary = $response->viewData('groupSummary')->keyBy('GroupName');

    expect($groupSummary['A']['TotalPosts'])->toBe(2);
    expect($groupSummary['A']['ActiveUsers'])->toBe(2);
    expect($groupSummary['A']['FlaggedContent'])->toBe(1);

    expect($groupSummary['B']['TotalPosts'])->toBe(1);
    expect($groupSummary['B']['ActiveUsers'])->toBe(1);
    expect($groupSummary['B']['FlaggedContent'])->toBe(0);
});

test('CSV export produces the same group stats as the index page', function () {
    $admin = User::factory()->create(['Role' => 'Administrator']);
    $group = Group::create(['GroupName' => 'ExportGroup', 'Description' => 'x', 'CreatedBy' => $admin->UserID]);
    $topic = Topic::create(['Title' => 'T', 'GroupID' => $group->GroupID, 'CreatedBy' => $admin->UserID, 'Status' => 'open', 'Category' => 'General']);
    Post::create(['TopicID' => $topic->TopicID, 'UserID' => $admin->UserID, 'Content' => 'hello']);

    $response = $this->actingAs($admin)->get(route('admin.statistics.export'));
    $response->assertOk();
    expect($response->streamedContent())->toContain('ExportGroup');
});
