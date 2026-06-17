<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\Quiz;
use App\Models\User;
use App\Models\UserChapterProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Course $course;
    private CourseChapter $chapter;
    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        // Run the seeders to populate initial data structures
        // or we can seed dynamically. Let's seed dynamically to keep it clean and fast.
        $this->course = Course::create([
            'slug' => 'advanced-sql',
            'points' => 50,
        ]);

        $this->course->translations()->create([
            'locale' => 'en',
            'title' => 'Advanced SQL',
            'description' => 'Deep dive into advanced topics.',
        ]);

        // Create category for the quiz
        $category = \App\Models\Category::firstOrCreate(['slug' => 'sql']);
        $category->translations()->firstOrCreate(
            ['locale' => 'en'],
            ['name' => 'SQL']
        );

        $this->quiz = Quiz::create([
            'slug' => 'course-advanced-sql-cte-recursion',
            'points' => 40,
            'category_id' => $category->id,
        ]);

        $this->quiz->translations()->create([
            'locale' => 'en',
            'title' => 'CTE & Recursion Quiz',
            'description' => 'Test your knowledge.',
        ]);

        // Create 2 questions for the quiz
        $q1 = $this->quiz->questions()->create([
            'type' => 'multiple_choice',
            'points' => 20,
            'correct_answer_index' => 0,
            'explanation' => 'Postgres 12 inlines by default if referenced once.',
        ]);
        $q1->translations()->create([
            'locale' => 'en',
            'question_text' => 'Postgres 12 materialization rule?',
            'options' => ['Not materialized by default', 'Materialized by default'],
        ]);

        $q2 = $this->quiz->questions()->create([
            'type' => 'multiple_choice',
            'points' => 20,
            'correct_answer_index' => 1,
            'explanation' => 'MySQL 8.0 does not support data modification in CTE.',
        ]);
        $q2->translations()->create([
            'locale' => 'en',
            'question_text' => 'Does MySQL support INSERT in CTE?',
            'options' => ['Yes', 'No'],
        ]);

        $this->chapter = CourseChapter::create([
            'course_id' => $this->course->id,
            'slug' => 'cte-recursion',
            'quiz_id' => $this->quiz->id,
            'order' => 1,
        ]);

        $this->chapter->translations()->create([
            'locale' => 'en',
            'title' => 'CTE & Recursion',
            'content_markdown' => '# CTE and Recursion theory',
        ]);

        $this->user = User::factory()->create([
            'points' => 0,
        ]);
    }

    public function test_guest_can_view_courses_index_page(): void
    {
        $response = $this->get('/en/courses');
        $response->assertOk();
        $response->assertSee('Advanced SQL');
    }

    public function test_guest_cannot_view_course_syllabus_and_is_redirected_to_login(): void
    {
        $response = $this->get('/en/courses/advanced-sql');
        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_view_course_chapter_and_is_redirected_to_login(): void
    {
        $response = $this->get('/en/courses/advanced-sql/cte-recursion');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_syllabus_and_chapter(): void
    {
        // 1. Syllabus page
        $response = $this->actingAs($this->user)->get('/en/courses/advanced-sql');
        $response->assertOk();
        $response->assertSee('Advanced SQL');
        $response->assertSee('CTE & Recursion');

        // 2. Chapter page
        $response = $this->actingAs($this->user)->get('/en/courses/advanced-sql/cte-recursion');
        $response->assertOk();
        $response->assertSee('CTE & Recursion');
        $response->assertSee('Postgres 12 materialization rule?');
    }

    public function test_authenticated_user_can_submit_quiz_answers_and_save_progress(): void
    {
        $questions = $this->quiz->questions;

        $response = $this->actingAs($this->user)->postJson('/en/courses/advanced-sql/cte-recursion/complete', [
            'answers' => [
                $questions[0]->id => 0, // correct
                $questions[1]->id => 1, // correct
            ]
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('points_scored', 40);

        // Verify user points updated
        $this->user->refresh();
        $this->assertEquals(40, $this->user->points);

        // Verify progress is saved in database with correct JSON log
        $this->assertDatabaseHas('user_chapter_progress', [
            'user_id' => $this->user->id,
            'course_chapter_id' => $this->chapter->id,
            'score' => 40,
        ]);

        $progress = UserChapterProgress::where('user_id', $this->user->id)
            ->where('course_chapter_id', $this->chapter->id)
            ->first();

        $this->assertNotNull($progress);
        $this->assertEquals(0, $progress->answers[$questions[0]->id]);
        $this->assertEquals(1, $progress->answers[$questions[1]->id]);

        // Verify user_quizzes records correct score
        $this->assertDatabaseHas('user_quizzes', [
            'user_id' => $this->user->id,
            'quiz_id' => $this->quiz->id,
            'score' => 40,
        ]);
    }

    public function test_submitting_partially_correct_quiz_saves_partial_score(): void
    {
        $questions = $this->quiz->questions;

        $response = $this->actingAs($this->user)->postJson('/en/courses/advanced-sql/cte-recursion/complete', [
            'answers' => [
                $questions[0]->id => 0, // correct (20 points)
                $questions[1]->id => 0, // incorrect
            ]
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('points_scored', 20);

        $this->user->refresh();
        $this->assertEquals(20, $this->user->points);
    }

    public function test_course_quizzes_are_excluded_from_public_quizzes_list(): void
    {
        // Add a standard public quiz
        $publicQuiz = Quiz::create([
            'slug' => 'php-basics-interview',
            'points' => 10,
        ]);
        $publicQuiz->translations()->create([
            'locale' => 'en',
            'title' => 'Standard PHP Quiz',
            'description' => 'A standard quiz.',
        ]);

        $response = $this->get('/en/quizzes');
        $response->assertOk();
        
        // Should see the standard public quiz
        $response->assertSee('Standard PHP Quiz');

        // Should NOT see the course-bound quiz
        $response->assertDontSee('CTE &amp; Recursion Quiz');
        $response->assertDontSee('course-advanced-sql-cte-recursion');
    }

    public function test_navigation_bar_contains_courses_and_jobs_links(): void
    {
        $response = $this->get('/en/courses');
        $response->assertOk();
        
        // Check for Courses links in header/footer
        $response->assertSee('/en/courses');
        // Check for Jobs links in header/footer
        $response->assertSee('/en/jobs');
    }
}
