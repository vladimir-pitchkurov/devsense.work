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
        if (!User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name'        => 'Vladimir Pitchkurov',
                'email'       => 'test@example.com',
                'role'        => User::ROLE_SUPER_ADMIN,
                'slug'        => 'vladimir-pitchkurov',
                'job_title'   => 'Senior PHP Engineer & Software Architect',
                'bio'         => "PHP developer with 10+ years of experience building high-load web applications, APIs, and distributed systems. Passionate about clean architecture, performance, and developer experience.\n\nI write in-depth guides on PHP internals, Laravel, microservices, and system design — focusing on practical patterns that hold up under real production conditions.",
                'github_url'  => 'https://github.com/pitchkurov',
                'linkedin_url'=> null,
                'twitter_url' => null,
                'website_url' => null,
            ]);
        }

        $this->call(QuizSeeder::class);
    }
}
