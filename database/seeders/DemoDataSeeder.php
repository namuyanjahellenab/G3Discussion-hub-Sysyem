<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Post;
use App\Models\Reply;
use App\Models\Topic;
use App\Models\TopicExclusion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Gets a fresh clone to a fully demoable state without any manual data
 * entry: real users across all three roles (with known login credentials,
 * documented in DEPLOYMENT.md), group memberships, topics with replies
 * (including an accepted answer and a threaded reply), one flagged reply
 * (so the moderation queue isn't empty), and one restricted-audience topic
 * (so topic_exclusions has something to show). Runs after GroupSeeder/
 * TopicSeeder, building on the groups/topics they create.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $lecturer = User::updateOrCreate(
            ['Email' => 'lecturer@demo.test'],
            [
                'UserName' => 'Dr. Amara Musoke',
                'PasswordHash' => Hash::make('password'),
                'Role' => 'Lecturer',
                'Status' => 'Active',
                'RulesAccepted' => true,
                'email_verified_at' => now(),
            ]
        );

        $admin = User::updateOrCreate(
            ['Email' => 'admin@demo.test'],
            [
                'UserName' => 'Demo Admin',
                'PasswordHash' => Hash::make('password'),
                'Role' => 'Administrator',
                'Status' => 'Active',
                'RulesAccepted' => true,
                'email_verified_at' => now(),
            ]
        );

        $studentNames = ['Kabaata Lubwama', 'Nakato Grace', 'Okello Peter'];
        $students = collect($studentNames)->map(fn ($name, $i) => User::updateOrCreate(
            ['Email' => 'student' . ($i + 1) . '@demo.test'],
            [
                'UserName' => $name,
                'PasswordHash' => Hash::make('password'),
                'Role' => 'Student',
                'Status' => 'Active',
                'RulesAccepted' => true,
                'email_verified_at' => now(),
            ]
        ));

        $algorithms = Group::where('GroupName', 'Algorithms')->first();
        $databases = Group::where('GroupName', 'Databases')->first();

        foreach ([$algorithms, $databases] as $group) {
            if (!$group) {
                continue;
            }
            foreach ($students as $student) {
                GroupStudent::firstOrCreate(
                    ['GroupID' => $group->GroupID, 'UserID' => $student->UserID],
                    ['Status' => 'active']
                );
            }
        }

        if ($algorithms) {
            // A normal, answered question with a threaded follow-up reply.
            $topic = Topic::firstOrCreate(
                ['GroupID' => $algorithms->GroupID, 'Title' => 'How does merge sort achieve O(n log n)?'],
                ['CreatedBy' => $students[0]->UserID, 'Status' => 'open', 'Category' => 'Algorithms']
            );
            $mainPost = Post::firstOrCreate(
                ['TopicID' => $topic->TopicID, 'UserID' => $students[0]->UserID],
                ['Content' => "I get that it's divide and conquer, but where does the log n actually come from?"]
            );
            $accepted = Reply::firstOrCreate(
                ['PostID' => $mainPost->PostID, 'UserID' => $lecturer->UserID],
                ['ReplyContent' => 'Each merge step is O(n), and the recursion halves the input each time — that halving is what gives you the log n factor.', 'IsAccepted' => true]
            );
            Reply::firstOrCreate(
                ['PostID' => $mainPost->PostID, 'UserID' => $students[1]->UserID, 'ParentReplyID' => $accepted->ReplyID],
                ['ReplyContent' => 'That clicked for me too once I drew the recursion tree out — thanks!']
            );

            // A restricted-audience topic (topic_exclusions has something to show).
            $restrictedTopic = Topic::firstOrCreate(
                ['GroupID' => $algorithms->GroupID, 'Title' => 'Group project pairing — please read'],
                ['CreatedBy' => $students[0]->UserID, 'Status' => 'open', 'Category' => 'Algorithms']
            );
            Post::firstOrCreate(
                ['TopicID' => $restrictedTopic->TopicID, 'UserID' => $students[0]->UserID],
                ['Content' => 'Sharing pairing preferences here — meant for everyone except the group already assigned.']
            );
            TopicExclusion::firstOrCreate([
                'TopicID' => $restrictedTopic->TopicID,
                'UserID' => $students[2]->UserID,
            ]);

            // A flagged reply (moderation queue has something to show).
            $flaggedTopic = Topic::firstOrCreate(
                ['GroupID' => $algorithms->GroupID, 'Title' => 'Anyone have notes from last lecture?'],
                ['CreatedBy' => $students[1]->UserID, 'Status' => 'open', 'Category' => 'Algorithms']
            );
            $flaggedMainPost = Post::firstOrCreate(
                ['TopicID' => $flaggedTopic->TopicID, 'UserID' => $students[1]->UserID],
                ['Content' => 'Missed the last session, can someone share their notes?']
            );
            $flaggedReply = Reply::firstOrCreate(
                ['PostID' => $flaggedMainPost->PostID, 'UserID' => $students[2]->UserID],
                ['ReplyContent' => 'Check out my notes site: totally-not-spam-earn-cash-fast.example — click here!']
            );
            $flaggedReply->flagBy($students[0]->UserID, 'Off-topic / spam link');
            $flaggedReply->flagBy($lecturer->UserID, 'Off-topic / spam link');
        }

        if ($lecturer) {
            Announcement::firstOrCreate(
                ['AuthorID' => $lecturer->UserID, 'GroupID' => $algorithms?->GroupID, 'Message' => 'Reminder: the algorithms midterm review session is this Friday at 4pm.'],
            );
        }
    }
}
