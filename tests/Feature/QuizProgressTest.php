<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Quiz;
use App\Models\User;
use App\Models\QuizInProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QuizProgressTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed Quiz & Questions
        $this->quiz = Quiz::create([
            'slug' => 'php-basics-interview',
            'points' => 100,
        ]);
        $this->quiz->translations()->create([
            'locale' => 'en',
            'title' => 'PHP Basics Interview',
            'description' => 'Test your basics.',
        ]);

        $q1 = $this->quiz->questions()->create([
            'type' => 'multiple_choice',
            'points' => 50,
            'correct_answer_index' => 1,
            'explanation' => 'Explain 1',
        ]);
        $q1->translations()->create([
            'locale' => 'en',
            'question_text' => 'Question 1',
            'options' => ['Option A', 'Option B'],
        ]);

        $q2 = $this->quiz->questions()->create([
            'type' => 'multiple_choice',
            'points' => 50,
            'correct_answer_index' => 0,
            'explanation' => 'Explain 2',
        ]);
        $q2->translations()->create([
            'locale' => 'en',
            'question_text' => 'Question 2',
            'options' => ['Option C', 'Option D'],
        ]);
    }

    public function test_authenticated_user_can_save_quiz_progress(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/en/quizzes/php-basics-interview/progress", [
                'question_index' => 1,
                'answers' => ['1' => 0],
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('quiz_in_progress', [
            'user_id' => $user->id,
            'quiz_id' => $this->quiz->id,
            'question_index' => 1,
        ]);
    }

    public function test_guest_cannot_save_quiz_progress(): void
    {
        $response = $this->postJson("/en/quizzes/php-basics-interview/progress", [
            'question_index' => 1,
            'answers' => ['1' => 0],
        ]);

        $response->assertStatus(401);
    }

    public function test_completing_quiz_deletes_progress(): void
    {
        $user = User::factory()->create();
        $questions = $this->quiz->questions;

        // Create initial progress
        QuizInProgress::create([
            'user_id' => $user->id,
            'quiz_id' => $this->quiz->id,
            'question_index' => 1,
            'answers' => [$questions[0]->id => 0],
            'saved_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/en/quizzes/php-basics-interview/complete", [
                'answers' => [
                    $questions[0]->id => 0,
                    $questions[1]->id => 0,
                ],
            ]);

        $response->assertOk();

        // Check if database has the quiz results
        $this->assertDatabaseHas('user_quizzes', [
            'user_id' => $user->id,
            'quiz_id' => $this->quiz->id,
        ]);

        // Check if database progress is deleted
        $this->assertDatabaseMissing('quiz_in_progress', [
            'user_id' => $user->id,
            'quiz_id' => $this->quiz->id,
        ]);
    }
}
