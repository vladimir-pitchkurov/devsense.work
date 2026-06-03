<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Article;
use App\Models\User;
use App\Models\Like;
use App\Models\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationAndLikesTest extends TestCase
{
    use RefreshDatabase;

    private User $reader;
    private User $author;
    private User $admin;
    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic writer badges
        $badge1 = Badge::create([
            'slug' => 'writer-novice',
            'points_required' => null,
            'articles_required' => 1,
            'image_path' => '/images/badges/writer-novice.svg',
        ]);
        $badge1->translations()->create(['locale' => 'en', 'title' => 'Novice Writer', 'description' => 'First article.']);

        $badge2 = Badge::create([
            'slug' => 'writer-prolific',
            'points_required' => null,
            'articles_required' => 5,
            'image_path' => '/images/badges/writer-prolific.svg',
        ]);
        $badge2->translations()->create(['locale' => 'en', 'title' => 'Prolific Writer', 'description' => '5 articles.']);

        // Create users
        $this->reader = User::factory()->create([
            'role' => User::ROLE_READER,
            'is_approved' => true,
        ]);

        $this->author = User::factory()->create([
            'role' => User::ROLE_AUTHOR,
            'is_approved' => true,
        ]);

        $this->admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_approved' => true,
        ]);

        // Create article
        $this->article = Article::create([
            'author_id' => $this->reader->id,
            'slug' => 'test-article-gamification',
            'is_published' => false,
            'is_approved' => false,
        ]);
        $this->article->translations()->create([
            'locale' => 'en',
            'title' => 'Test Article',
            'content' => 'Test Content',
        ]);
    }

    public function test_new_user_registration_defaults_to_reader_and_redirects_to_cabinet_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Reader',
            'email' => 'jane@devsense.work',
            'password' => 'secret-pwd-123',
            'password_confirmation' => 'secret-pwd-123',
            'terms' => 'on',
        ]);

        $response->assertRedirect('/en/admin');

        $user = User::where('email', 'jane@devsense.work')->first();
        $this->assertNotNull($user);
        $this->assertEquals(User::ROLE_READER, $user->role);
        $this->assertFalse($user->is_approved);
    }

    public function test_cabinet_dashboard_renders_user_dashboard_for_non_admins(): void
    {
        $response = $this->actingAs($this->reader)->get('/en/admin');
        $response->assertStatus(200);
        $response->assertViewIs('admin.user_dashboard');
        $response->assertSee('Active Challenges');

        $response2 = $this->actingAs($this->author)->get('/en/admin');
        $response2->assertStatus(200);
        $response2->assertViewIs('admin.user_dashboard');
    }

    public function test_cabinet_dashboard_renders_statistics_for_super_admin(): void
    {
        $response = $this->actingAs($this->admin)->get('/en/admin');
        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');
        $response->assertSee('Analytics Dashboard');
    }

    public function test_moderation_routes_are_denied_for_simple_users(): void
    {
        // Try to approve self
        $response = $this->actingAs($this->author)->post(route('admin.moderation.authors.approve', [
            'locale' => 'en',
            'user' => $this->reader->id,
        ]));
        $response->assertStatus(403);
    }

    public function test_category_management_is_denied_for_simple_users(): void
    {
        $response = $this->actingAs($this->author)->get('/en/admin/categories');
        $response->assertStatus(403);
    }

    public function test_article_approval_promotes_reader_to_author_and_awards_xp_and_badges(): void
    {
        $this->assertEquals(User::ROLE_READER, $this->reader->role);
        $this->assertEquals(0, $this->reader->points);
        $this->assertCount(0, $this->reader->badges);

        // Approve and publish the article
        $this->article->update([
            'is_approved' => true,
            'is_published' => true,
        ]);

        $this->reader->refresh();

        // 1. Role promoted
        $this->assertEquals(User::ROLE_AUTHOR, $this->reader->role);

        // 2. XP awarded (+100 XP)
        $this->assertEquals(100, $this->reader->points);

        // 3. Novice Writer badge unlocked
        $this->assertCount(1, $this->reader->badges);
        $this->assertEquals('writer-novice', $this->reader->badges->first()->slug);
    }

    public function test_article_xp_is_awarded_only_once(): void
    {
        $this->article->update([
            'is_approved' => true,
            'is_published' => true,
        ]);

        $this->reader->refresh();
        $this->assertEquals(100, $this->reader->points);

        // Unpublish and publish again
        $this->article->update(['is_published' => false]);
        $this->article->update(['is_published' => true]);

        $this->reader->refresh();
        $this->assertEquals(100, $this->reader->points); // Points remain 100, not double awarded
    }

    public function test_like_and_dislike_functionality(): void
    {
        // 1. Guest cannot react (gets redirect / error)
        $response = $this->postJson(route('likes.toggle', ['locale' => 'en']), [
            'likeable_id' => $this->article->id,
            'likeable_type' => 'article',
            'is_dislike' => false,
        ]);
        $response->assertStatus(401);

        // 2. Logged-in user can like
        $response = $this->actingAs($this->reader)->postJson(route('likes.toggle', ['locale' => 'en']), [
            'likeable_id' => $this->article->id,
            'likeable_type' => 'article',
            'is_dislike' => false,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'added',
            'likes_count' => 1,
            'dislikes_count' => null, // Hidden from reader
        ]);

        $this->assertEquals(1, $this->article->likesCount());
        $this->assertEquals(0, $this->article->dislikesCount());

        // 3. Clicking like again toggles it off
        $response = $this->actingAs($this->reader)->postJson(route('likes.toggle', ['locale' => 'en']), [
            'likeable_id' => $this->article->id,
            'likeable_type' => 'article',
            'is_dislike' => false,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'removed',
            'likes_count' => 0,
        ]);

        $this->assertEquals(0, $this->article->likesCount());

        // 4. Liking then disliking switches reaction
        $this->actingAs($this->reader)->postJson(route('likes.toggle', ['locale' => 'en']), [
            'likeable_id' => $this->article->id,
            'likeable_type' => 'article',
            'is_dislike' => false,
        ]); // Liked

        $response = $this->actingAs($this->reader)->postJson(route('likes.toggle', ['locale' => 'en']), [
            'likeable_id' => $this->article->id,
            'likeable_type' => 'article',
            'is_dislike' => true,
        ]); // Disliked
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'switched',
            'likes_count' => 0,
        ]);

        $this->assertEquals(0, $this->article->likesCount());
        $this->assertEquals(1, $this->article->dislikesCount());

        // 5. Super admin can see dislike counts
        $response = $this->actingAs($this->admin)->postJson(route('likes.toggle', ['locale' => 'en']), [
            'likeable_id' => $this->article->id,
            'likeable_type' => 'article',
            'is_dislike' => true,
        ]);
        $response->assertJson([
            'success' => true,
            'likes_count' => 0,
            'dislikes_count' => 2, // Visible to admin
        ]);
    }
}
