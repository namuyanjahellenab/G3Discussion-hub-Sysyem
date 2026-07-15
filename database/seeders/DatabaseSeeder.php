<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed groups first, then topics, then demo users/content so a
        // fresh clone is immediately demoable without manual data entry.
        $this->call(GroupSeeder::class);
        $this->call(TopicSeeder::class);
        $this->call(DemoDataSeeder::class);
    }
}


