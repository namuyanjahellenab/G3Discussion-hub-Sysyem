<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            // GroupName => Description (CSC code)
            'Algorithms' => 'CSC301',
            'Databases' => 'CSC302',
            'Software Engineering' => 'CSC303',
            'Networks' => 'CSC304',

            // Also support common variant label
            'Software Eng.' => 'CSC303',
        ];

        foreach ($groups as $groupName => $courseCode) {
            Group::updateOrCreate(
                ['GroupName' => $groupName],
                [
                    'Description' => $courseCode,
                    'CreatedBy' => 'System',
                ]
            );
        }
    }
}




