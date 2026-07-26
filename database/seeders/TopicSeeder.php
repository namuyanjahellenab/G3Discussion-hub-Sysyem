<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;

class TopicSeeder extends Seeder
{
    public function run(): void
    {
        // Define one topic per group - topic name matches group name
        $groupTopics = [
            'Algorithms' => 'Algorithms',
            'Networks' => 'Networks',
            'Databases' => 'Databases',
            'Software Engineering' => 'Programming',
            
        ];

        // Get or create a system user for seeding
        $systemUser = User::where('Email', 'system@example.com')->first();
        if (!$systemUser) {
            $systemUser = User::create([
                'UserName' => 'System',
                'Email' => 'system@example.com',
                'PasswordHash' => bcrypt('system123'),
                // Every role check in this app is against the literal string
                // 'Administrator' (EnsureUserIsAdmin middleware, announcement/
                // moderation gates, etc.) — 'Admin' silently granted this
                // account zero actual admin privileges.
                'Role' => 'Administrator',
                'Status' => 'Active',
                'RulesAccepted' => true,
            ]);
        }

        foreach ($groupTopics as $groupName => $topicTitle) {
            // Find the group
            $group = Group::where('GroupName', $groupName)->first();
            
            if ($group) {
                // Create or update the single topic for this group
                Topic::updateOrCreate(
                    [
                        'GroupID' => $group->GroupID,
                        'Title' => $topicTitle,
                    ],
                    [
                        'CreatedBy' => $systemUser->UserID,
                        'Category' => $groupName,
                    ]
                );
            }
        }
    }
}
