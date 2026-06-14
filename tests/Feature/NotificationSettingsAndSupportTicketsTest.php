<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\Article;
use App\Models\Quiz;
use App\Models\ArticleSuggestion;
use App\Models\ArticleSuggestionComment;
use App\Models\SupportTicket;
use App\Mail\NewArticleMail;
use App\Mail\NewQuizMail;
use App\Mail\NewCommentMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationSettingsAndSupportTicketsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Onboarding screen renders and submits successfully.
     */
    public function test_onboarding_flow(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'locale' => 'en',
        ]);

        $category = Category::factory()->create(['slug' => 'php']);
        $category->translations()->create(['locale' => 'en', 'name' => 'PHP']);

        $response = $this->actingAs($user)->get('/en/onboarding');
        $response->assertStatus(200);
        $response->assertSee('Welcome to DevSense!');
        $response->assertSee('PHP');

        $response = $this->actingAs($user)->post('/en/onboarding', [
            'locale' => 'ru',
            'interests' => [$category->id],
            'notify_articles_quizzes' => '1',
            'notify_comments' => '1',
        ]);

        $response->assertRedirect('/ru/admin');
        
        $user->refresh();
        $this->assertEquals('ru', $user->locale);
        $this->assertTrue($user->notify_articles_quizzes);
        $this->assertTrue($user->notify_comments);
        $this->assertCount(1, $user->interests);
    }

    /**
     * Test redirection to onboarding from dashboard if needs_onboarding session flag is set.
     */
    public function test_dashboard_redirects_to_onboarding(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['needs_onboarding' => true])
            ->get('/en/admin');

        $response->assertRedirect('/en/onboarding');
    }

    /**
     * Test notification delivery constraints.
     */
    public function test_notification_delivery_constraints(): void
    {
        Mail::fake();

        $category = Category::factory()->create(['slug' => 'php']);
        
        // 1. Verified user interested in PHP
        $verifiedUser = User::factory()->create([
            'email_verified_at' => now(),
            'locale' => 'ru',
            'notify_articles_quizzes' => true,
        ]);
        $verifiedUser->interests()->attach($category->id);

        // 2. Unverified user interested in PHP
        $unverifiedUser = User::factory()->create([
            'email_verified_at' => null,
            'locale' => 'ru',
            'notify_articles_quizzes' => true,
        ]);
        $unverifiedUser->interests()->attach($category->id);

        // 3. Verified user but notifications disabled
        $disabledUser = User::factory()->create([
            'email_verified_at' => now(),
            'locale' => 'ru',
            'notify_articles_quizzes' => false,
        ]);
        $disabledUser->interests()->attach($category->id);

        // 4. Verified user but cooldown active (last notified 2 days ago)
        $cooldownUser = User::factory()->create([
            'email_verified_at' => now(),
            'locale' => 'ru',
            'notify_articles_quizzes' => true,
            'last_notified_at' => now()->subDays(2),
        ]);
        $cooldownUser->interests()->attach($category->id);

        $article = Article::factory()->create([
            'is_approved' => true,
            'is_published' => true,
        ]);
        $article->categories()->attach($category->id);

        // Run notification dispatcher
        app(\App\Services\NotificationService::class)->notifyNewArticle($article);

        // Verify who got the mail
        Mail::assertSent(NewArticleMail::class, function ($mail) use ($verifiedUser) {
            return $mail->hasTo($verifiedUser->email) && $mail->user->locale === 'ru';
        });

        Mail::assertNotSent(NewArticleMail::class, function ($mail) use ($unverifiedUser) {
            return $mail->hasTo($unverifiedUser->email);
        });

        Mail::assertNotSent(NewArticleMail::class, function ($mail) use ($disabledUser) {
            return $mail->hasTo($disabledUser->email);
        });

        Mail::assertNotSent(NewArticleMail::class, function ($mail) use ($cooldownUser) {
            return $mail->hasTo($cooldownUser->email);
        });

        // Verify last_notified_at got updated
        $verifiedUser->refresh();
        $this->assertNotNull($verifiedUser->last_notified_at);
    }

    /**
     * Test Support Tickets tab and dashboard rendering.
     */
    public function test_support_tickets_on_dashboard(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        
        $ticket = SupportTicket::create([
            'sender_email' => 'user@devsense.work',
            'sender_name' => 'John Doe',
            'subject' => 'Help me',
            'message' => 'I cannot log in',
            'type' => 'complaint',
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin)->get('/en/admin');
        $response->assertStatus(200);
        $response->assertSee('Support Tickets (1)');
        $response->assertSee('Help me');
    }

    /**
     * Test inline quick approve button in users index.
     */
    public function test_quick_user_approvals(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        
        $verifiedUser = User::factory()->create([
            'email_verified_at' => now(),
            'is_approved' => false,
        ]);

        $unverifiedUser = User::factory()->create([
            'email_verified_at' => null,
            'is_approved' => false,
        ]);

        $response = $this->actingAs($admin)->get('/en/admin/users');
        $response->assertStatus(200);
        $response->assertSee('Verified');
        $response->assertSee('Unverified');

        // Approve verified user succeeds
        $response = $this->actingAs($admin)->post("/en/admin/moderation/authors/{$verifiedUser->id}/approve");
        $response->assertRedirect();
        $this->assertTrue($verifiedUser->refresh()->is_approved);

        // Approve unverified user fails
        $response = $this->actingAs($admin)->post("/en/admin/moderation/authors/{$unverifiedUser->id}/approve");
        $response->assertRedirect();
        $this->assertFalse($unverifiedUser->refresh()->is_approved);
    }
}
