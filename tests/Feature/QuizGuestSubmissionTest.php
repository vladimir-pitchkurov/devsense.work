<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QuizGuestSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;

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
    }

    public function test_guest_can_complete_quiz_and_data_is_saved_in_session(): void
    {
        $questions = $this->quiz->questions;

        $response = $this->postJson("/en/quizzes/php-8-4-hooks/complete", [
            'answers' => [
                $questions[0]->id => 1, // correct
                $questions[1]->id => 2, // correct
            ]
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('is_guest', true);
        $response->assertJsonPath('points_scored', 50);

        // Assert session has pending_quiz
        $this->assertEquals([
            'slug' => 'php-8-4-hooks',
            'answers' => [
                $questions[0]->id => 1,
                $questions[1]->id => 2,
            ]
        ], session('pending_quiz'));
    }

    public function test_guest_logging_in_after_quiz_submits_pending_quiz(): void
    {
        $questions = $this->quiz->questions;
        $user = User::factory()->create([
            'email' => 'candidate@devsense.work',
            'password' => Hash::make('secret-pwd'),
            'points' => 0,
        ]);

        // Put pending quiz in session
        $sessionData = [
            'slug' => 'php-8-4-hooks',
            'answers' => [
                $questions[0]->id => 1, // correct
                $questions[1]->id => 2, // correct
            ]
        ];

        $response = $this->withSession(['pending_quiz' => $sessionData])
            ->post('/login', [
                'email' => 'candidate@devsense.work',
                'password' => 'secret-pwd',
            ]);

        $response->assertRedirect('/en/quizzes/php-8-4-hooks');

        // Check if database has the quiz results
        $this->assertDatabaseHas('user_quizzes', [
            'user_id' => $user->id,
            'quiz_id' => $this->quiz->id,
            'score' => 50,
        ]);

        $user->refresh();
        $this->assertEquals(50, $user->points);
        $this->assertTrue($user->badges->contains('slug', 'php-novice'));

        // Check if session flashed data is set
        $this->assertEquals('php-8-4-hooks', session('quiz_completed_from_guest'));
        $this->assertEquals($sessionData['answers'], session('quiz_user_answers'));
    }

    public function test_guest_registering_after_quiz_submits_pending_quiz(): void
    {
        $questions = $this->quiz->questions;

        // Put pending quiz in session
        $sessionData = [
            'slug' => 'php-8-4-hooks',
            'answers' => [
                $questions[0]->id => 1, // correct
                $questions[1]->id => 2, // correct
            ]
        ];

        $response = $this->withSession(['pending_quiz' => $sessionData])
            ->post('/register', [
                'name' => 'John Guest',
                'email' => 'john.guest@devsense.work',
                'password' => 'secret-pwd-123',
                'password_confirmation' => 'secret-pwd-123',
                'terms' => 'on',
            ]);

        $response->assertRedirect('/en/quizzes/php-8-4-hooks');

        $user = User::where('email', 'john.guest@devsense.work')->first();
        $this->assertNotNull($user);

        // Check if database has the quiz results
        $this->assertDatabaseHas('user_quizzes', [
            'user_id' => $user->id,
            'quiz_id' => $this->quiz->id,
            'score' => 50,
        ]);

        $user->refresh();
        $this->assertEquals(50, $user->points);
        $this->assertTrue($user->badges->contains('slug', 'php-novice'));

        // Check if session flashed data is set
        $this->assertEquals('php-8-4-hooks', session('quiz_completed_from_guest'));
        $this->assertEquals($sessionData['answers'], session('quiz_user_answers'));
    }
}
