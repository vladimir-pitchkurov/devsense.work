<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VipArticleAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private Category $securityCategory;
    private Article $securityArticle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->admin()->create([
            'name' => 'Security Expert',
            'slug' => 'security-expert',
        ]);

        $this->securityCategory = Category::create(['slug' => 'security']);
        $this->securityCategory->translations()->create(['locale' => 'en', 'name' => 'Security']);

        // Link with an actual file on disk to prevent MarkdownContentService from aborting with 404
        $this->securityArticle = Article::create([
            'slug' => 'advanced-file-upload-attacks',
            'author_id' => $this->author->id,
            'category_id' => $this->securityCategory->id,
            'is_published' => true,
            'is_approved' => true,
            'published_at' => now(),
        ]);
        $this->securityArticle->translations()->create([
            'locale' => 'en',
            'title' => 'Advanced File Upload Attacks',
            'description' => 'Advanced attacks',
            'content' => "<p>First paragraph here.</p>\n\n<p>Second paragraph here.</p>\n\n<p>Third paragraph with UUID renaming information.</p>",
        ]);
    }

    public function test_guest_sees_truncated_preview_and_vip_login_notice(): void
    {
        $response = $this->get('/en/security/advanced-file-upload-attacks');
        $response->assertStatus(200);
        $response->assertSee('🔒 Restricted Article');
        $response->assertSee('Sign In to Access');
        $response->assertSee('...');
        // Should not see full deep technical content keywords (e.g. secure coding snippet or Ghostscript details)
        $response->assertDontSee('UUID renaming');
    }

    public function test_logged_in_non_vip_user_sees_truncated_preview_and_vip_pending_badge(): void
    {
        $user = User::factory()->create(['is_vip' => false, 'vip_requested_at' => now()]);
        $this->actingAs($user);

        $response = $this->get('/en/security/advanced-file-upload-attacks');
        $response->assertStatus(200);
        $response->assertSee('🔒 Restricted Article');
        $response->assertSee('Access Pending Approval');
        $response->assertSee('...');
        $response->assertDontSee('UUID renaming');
    }

    public function test_logged_in_vip_user_sees_full_article(): void
    {
        $vipUser = User::factory()->create(['is_vip' => true]);
        $this->actingAs($vipUser);

        $response = $this->get('/en/security/advanced-file-upload-attacks');
        $response->assertStatus(200);
        $response->assertDontSee('🔒 Restricted Article');
        $response->assertSee('UUID renaming'); // Full content keyword
    }

    public function test_admin_user_sees_full_article(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->get('/en/security/advanced-file-upload-attacks');
        $response->assertStatus(200);
        $response->assertDontSee('🔒 Restricted Article');
        $response->assertSee('UUID renaming');
    }

    public function test_author_sees_their_own_article(): void
    {
        $this->actingAs($this->author);

        $response = $this->get('/en/security/advanced-file-upload-attacks');
        $response->assertStatus(200);
        $response->assertDontSee('🔒 Restricted Article');
        $response->assertSee('UUID renaming');
    }

    public function test_vip_request_workflow(): void
    {
        $user = User::factory()->create(['is_vip' => false, 'vip_requested_at' => null]);
        $this->actingAs($user);

        // 1. Initial load shows Request button
        $response = $this->get('/en/security/advanced-file-upload-attacks');
        $response->assertStatus(200);
        $response->assertSee('Request VIP Access');
        $response->assertDontSee('Access Pending Approval');

        // 2. Submit request
        $postResponse = $this->post(route('vip.request', ['locale' => 'en']));
        $postResponse->assertSessionHas('success');

        $user->refresh();
        $this->assertNotNull($user->vip_requested_at);
        $this->assertFalse($user->is_vip);

        // 3. Page load shows Pending Approval badge
        $response2 = $this->get('/en/security/advanced-file-upload-attacks');
        $response2->assertStatus(200);
        $response2->assertDontSee('Request VIP Access');
        $response2->assertSee('Access Pending Approval');

        // 4. Admin sees user's pending request in index and filters
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $indexResponse = $this->get(route('admin.users.index', ['locale' => 'en', 'vip' => 'requested']));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee($user->name);
        $indexResponse->assertSee('VIP Request');

        // 5. Admin approves VIP request
        $updateResponse = $this->put(route('admin.users.update', ['locale' => 'en', 'user' => $user->id]), [
            'role' => $user->role,
            'is_vip' => '1',
        ]);
        $updateResponse->assertRedirect(route('admin.users.index', ['locale' => 'en']));

        $user->refresh();
        $this->assertTrue($user->is_vip);
        $this->assertNull($user->vip_requested_at);
    }
}
