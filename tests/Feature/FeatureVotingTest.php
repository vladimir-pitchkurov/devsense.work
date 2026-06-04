<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureVotingTest extends TestCase
{
    use RefreshDatabase;

    private Feature $feature;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test feature
        $this->feature = Feature::create([
            'slug' => 'interactive-courses',
            'title_en' => 'Interactive Courses',
            'title_ru' => 'Интерактивные курсы',
            'description_en' => 'Structured developer courses.',
            'description_ru' => 'Интерактивные курсы.',
        ]);
    }

    public function test_guest_cannot_vote_on_feature(): void
    {
        // Try requesting via standard POST - should redirect to login
        $response = $this->post("/en/features/{$this->feature->id}/vote");
        $response->assertRedirect('/login');

        // Try requesting via JSON POST - should receive 401
        $responseJson = $this->postJson("/en/features/{$this->feature->id}/vote");
        $responseJson->assertStatus(401);
    }

    public function test_registered_user_can_view_features_list(): void
    {
        $user = User::factory()->create(['points' => 150]); // voting power = 2

        $response = $this->actingAs($user)->get('/en/features');

        $response->assertOk();
        $response->assertSee('Roadmap');
        $response->assertSee('Interactive Courses');
        $response->assertSee('+2 votes');
    }

    public function test_user_can_toggle_vote_and_weight_is_calculated_correctly(): void
    {
        $user1 = User::factory()->create(['points' => 50]); // weight = 1
        $user2 = User::factory()->create(['points' => 250]); // weight = 3

        // 1. User 1 votes
        $response = $this->actingAs($user1)->postJson("/en/features/{$this->feature->id}/vote");
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'voted' => true,
            'total_count' => 1,
            'total_weight' => 1,
        ]);

        $this->assertDatabaseHas('feature_votes', [
            'user_id' => $user1->id,
            'feature_id' => $this->feature->id,
        ]);

        // 2. User 2 votes
        $response = $this->actingAs($user2)->postJson("/en/features/{$this->feature->id}/vote");
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'voted' => true,
            'total_count' => 2,
            'total_weight' => 4, // 1 + 3
        ]);

        // 3. User 1 retracts vote (toggle off)
        $response = $this->actingAs($user1)->postJson("/en/features/{$this->feature->id}/vote");
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'voted' => false,
            'total_count' => 1,
            'total_weight' => 3, // only User 2 left
        ]);

        $this->assertDatabaseMissing('feature_votes', [
            'user_id' => $user1->id,
            'feature_id' => $this->feature->id,
        ]);
    }

    public function test_vote_weight_updates_dynamically_when_user_earns_points(): void
    {
        $user = User::factory()->create(['points' => 50]); // weight = 1

        // Vote
        $this->actingAs($user)->postJson("/en/features/{$this->feature->id}/vote");
        $this->assertEquals(1, $this->feature->totalVotesWeight());

        // Earn points (XP increases)
        $user->points = 350; // weight = 4
        $user->save();

        // Check if weight recalculated automatically on model call
        $this->assertEquals(4, $this->feature->totalVotesWeight());
    }
}
