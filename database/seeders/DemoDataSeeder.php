<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the one bootstrap account per role needed to get into a fresh
 * deployment: an Administrator (who can then create/promote further
 * admins/lecturers from within the app), a Lecturer, and a Student.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $sharedPassword = Hash::make('Discuss2026!');

        User::updateOrCreate(
            ['Email' => 'admin@system.com'],
            [
                'UserName' => 'Administrator',
                'PasswordHash' => $sharedPassword,
                'Role' => 'Administrator',
                'Status' => 'Active',
                'RulesAccepted' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['Email' => 'lecturer@gmail.com'],
            [
                'UserName' => 'Lecturer',
                'PasswordHash' => $sharedPassword,
                'Role' => 'Lecturer',
                'Status' => 'Active',
                'RulesAccepted' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['Email' => 'student@mak.ug'],
            [
                'UserName' => 'Student',
                'PasswordHash' => $sharedPassword,
                'Role' => 'Student',
                'Status' => 'Active',
                'RulesAccepted' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
