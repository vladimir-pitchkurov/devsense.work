<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\User;
use App\Models\ArticleSuggestion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ArticleSuggestionSeeder extends Seeder
{
    public function run(): void
    {
        // First ensure some mock users exist
        $users = [];
        
        $user1 = User::firstOrCreate(
            ['email' => 'reader1@example.com'],
            [
                'name' => 'Alice Dev',
                'password' => Hash::make('password'),
                'role' => User::ROLE_READER,
                'slug' => 'alice-dev',
                'is_public' => true,
                'is_blocked' => false,
            ]
        );
        $users[] = $user1;

        $user2 = User::firstOrCreate(
            ['email' => 'reader2@example.com'],
            [
                'name' => 'Bob Builder',
                'password' => Hash::make('password'),
                'role' => User::ROLE_READER,
                'slug' => 'bob-builder',
                'is_public' => true,
                'is_blocked' => false,
            ]
        );
        $users[] = $user2;

        $user3 = User::firstOrCreate(
            ['email' => 'author1@example.com'],
            [
                'name' => 'Charlie Coder',
                'password' => Hash::make('password'),
                'role' => User::ROLE_AUTHOR,
                'slug' => 'charlie-coder',
                'is_public' => true,
                'is_blocked' => false,
            ]
        );
        $users[] = $user3;

        // Ensure we have Vladimir Pitchkurov too
        $vladimir = User::where('role', User::ROLE_SUPER_ADMIN)->first();
        if ($vladimir) {
            $users[] = $vladimir;
        }

        // Get all articles (make sure they are imported)
        $articles = Article::all();
        if ($articles->isEmpty()) {
            // Run the migration command to populate articles
            \Illuminate\Support\Facades\Artisan::call('app:migrate-articles-to-database');
            $articles = Article::all();
        }

        if ($articles->isEmpty()) {
            return;
        }

        // Seed some suggestions for the first few articles
        foreach ($articles->take(3) as $article) {
            // Suggestion 1: Typo / spelling check
            $suggestion1 = ArticleSuggestion::create([
                'article_id' => $article->id,
                'user_id' => $user1->id,
                'content' => "There seems to be a minor typo in the introduction section of the article. Let's fix 'deprecatons' to 'deprecations'. Excellent guide overall!",
                'status' => 'pending',
            ]);

            // Add votes for suggestion 1
            $suggestion1->votes()->create(['user_id' => $user2->id]);
            $suggestion1->votes()->create(['user_id' => $user3->id]);

            // Add comments for suggestion 1
            $suggestion1->comments()->create([
                'user_id' => $user3->id,
                'content' => "Good catch, Alice! I'll update it as soon as the author reviews it.",
            ]);

            if ($vladimir) {
                $suggestion1->comments()->create([
                    'user_id' => $vladimir->id,
                    'content' => "Fixed. Thanks for pointing it out!",
                ]);
                $suggestion1->update(['status' => 'implemented']);
            }

            // Suggestion 2: Feature addition request
            $suggestion2 = ArticleSuggestion::create([
                'article_id' => $article->id,
                'user_id' => $user2->id,
                'content' => "Could you add a code snippet demonstrating the performance difference when using PgBouncer in transaction pooling mode versus session pooling mode? That would make this guide even more complete.",
                'status' => 'approved',
            ]);

            // Add votes for suggestion 2
            $suggestion2->votes()->create(['user_id' => $user1->id]);

            // Add comments for suggestion 2
            $suggestion2->comments()->create([
                'user_id' => $user3->id,
                'content' => "Totally support this! Session pooling performance is very different under high numbers of concurrent client connections.",
            ]);
        }
    }
}
