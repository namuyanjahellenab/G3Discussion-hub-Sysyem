<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Services\GroupBrowseService;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'Algorithms' => 'Explore algorithm design, complexity analysis, and problem-solving techniques '
                . 'covering sorting, searching, recursion, and dynamic programming.',
            'Databases' => 'Learn relational database design, SQL querying, normalization, indexing, and '
                . 'transaction management using real-world schemas.',
            'Software Engineering' => 'Covers the software development lifecycle, design patterns, system '
                . 'architecture, agile practices, and version control workflows.',
            'Networks' => 'Study computer networking fundamentals, including TCP/IP, routing, sockets, and '
                . 'client-server communication protocols.',

            // Also support common variant label
            'Software Eng.' => 'Covers the software development lifecycle, design patterns, system '
                . 'architecture, agile practices, and version control workflows.',
        ];

        $browseService = new GroupBrowseService();

        foreach ($groups as $groupName => $description) {
            $group = Group::updateOrCreate(
                ['GroupName' => $groupName],
                [
                    'Description' => $description,
                    'CreatedBy' => 'System',
                ]
            );

            // Auto-generated from initials + ID, same as every group created
            // through the admin panel - no course code is hand-typed/seeded.
            $group->update(['CourseCode' => $browseService->deriveGroupCode($group)]);
        }
    }
}




