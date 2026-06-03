<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizSystemTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed Badges
        $badge = Badge::create([
            'slug' => 'php-novice',
            'points_required' => 50,
            'image_path' => '/images/badges/php-novice.svg',
        ]);
        $badge->translations()->create([
            'locale' => 'en',
            'title' => 'PHP Novice',
            'description' => 'Scored 50+ total points in quizzes.'
        ]);

        // Seed Quiz & Questions
        $this->quiz = Quiz::create([
            'slug' => 'php-8-4-hooks',
            'points' => 50,
        ]);
        $this->quiz->translations()->create([
            'locale' => 'en',
            'title' => 'PHP 8.4 Properties & Hooks',
            'description' => 'Test your knowledge.',
        ]);

        $q1 = $this->quiz->questions()->create([
            'type' => 'multiple_choice',
            'points' => 25,
            'correct_answer_index' => 1,
            'explanation' => 'No hooks on readonly.',
        ]);
        $q1->translations()->create([
            'locale' => 'en',
            'question_text' => 'Readonly hooks?',
            'options' => ['Yes', 'No'],
        ]);

        $q2 = $this->quiz->questions()->create([
            'type' => 'multiple_choice',
            'points' => 25,
            'correct_answer_index' => 2,
            'explanation' => '$value is automatically provided.',
        ]);
        $q2->translations()->create([
            'locale' => 'en',
            'question_text' => 'Variable name?',
            'options' => ['$this', '$val', '$value'],
        ]);

        $this->user = User::factory()->create([
            'points' => 0,
        ]);
    }

    public function test_guest_can_view_quizzes_index(): void
    {
        $response = $this->get('/en/quizzes');
        $response->assertOk();
        $response->assertSee('Interview Quizzes & Gamification');
        $response->assertSee('PHP 8.4 Properties & Hooks');
        $response->assertSee('Want to track your progress?');
    }

    public function test_authenticated_user_can_view_quizzes_index(): void
    {
        $response = $this->actingAs($this->user)->get('/en/quizzes');
        $response->assertOk();
        $response->assertSee('0'); // 0 points
        $response->assertSee('Achievement Progress');
    }

    public function test_user_can_view_quiz(): void
    {
        $response = $this->get('/en/quizzes/php-8-4-hooks');
        $response->assertOk();
        $response->assertSee('PHP 8.4 Properties & Hooks');
    }

    public function test_authenticated_user_can_complete_quiz_and_earn_points(): void
    {
        $questions = $this->quiz->questions;

        $response = $this->actingAs($this->user)->postJson("/en/quizzes/php-8-4-hooks/complete", [
            'answers' => [
                $questions[0]->id => 1, // correct
                $questions[1]->id => 2, // correct
            ]
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('points_scored', 50);
        $response->assertJsonPath('total_points', 50);

        // Verify database updates
        $this->user->refresh();
        $this->assertEquals(50, $this->user->points);
        $this->assertDatabaseHas('user_quizzes', [
            'user_id' => $this->user->id,
            'quiz_id' => $this->quiz->id,
            'score' => 50,
        ]);
    }

    public function test_completing_quiz_unlocks_badge(): void
    {
        $questions = $this->quiz->questions;

        $response = $this->actingAs($this->user)->postJson("/en/quizzes/php-8-4-hooks/complete", [
            'answers' => [
                $questions[0]->id => 1, // correct
                $questions[1]->id => 2, // correct
            ]
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'new_badges');
        $response->assertJsonPath('new_badges.0.title', 'PHP Novice');

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $this->user->id,
            'badge_id' => Badge::where('slug', 'php-novice')->first()->id,
        ]);
    }

    public function test_partially_correct_quiz_saves_partial_points(): void
    {
        $questions = $this->quiz->questions;

        $response = $this->actingAs($this->user)->postJson("/en/quizzes/php-8-4-hooks/complete", [
            'answers' => [
                $questions[0]->id => 1, // correct (25 XP)
                $questions[1]->id => 0, // incorrect
            ]
        ]);

        $response->assertOk();
        $response->assertJsonPath('points_scored', 25);
        
        $this->user->refresh();
        $this->assertEquals(25, $this->user->points);
        $response->assertJsonCount(0, 'new_badges'); // requires 50
    }
}
