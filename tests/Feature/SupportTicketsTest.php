<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SupportTicket;
use App\Mail\SupportTicketReplyMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupportTicketsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the inbound webhook successfully creates a support ticket.
     */
    public function test_inbound_webhook_can_create_ticket(): void
    {
        $payload = [
            'type' => 'email.received',
            'data' => [
                'from' => 'John Doe <john@example.com>',
                'subject' => 'This is a suggestion for the website',
                'text' => 'I suggest we add dark mode everywhere.',
                'html' => '<p>I suggest we add dark mode everywhere.</p>',
            ]
        ];

        $response = $this->postJson(route('api.webhooks.resend-inbound'), $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'ticket_id']);

        $this->assertDatabaseHas('support_tickets', [
            'sender_email' => 'john@example.com',
            'sender_name' => 'John Doe',
            'subject' => 'This is a suggestion for the website',
            'message' => 'I suggest we add dark mode everywhere.',
            'type' => 'suggestion',
            'status' => 'open',
        ]);
    }

    /**
     * Test that the inbound webhook processes and stores email attachments.
     */
    public function test_inbound_webhook_stores_attachments(): void
    {
        \Illuminate\Support\Facades\Storage::fake();

        $payload = [
            'type' => 'email.received',
            'data' => [
                'from' => 'attachment.user@example.com',
                'subject' => 'Ticket with files',
                'text' => 'See attached file.',
                'attachments' => [
                    [
                        'name' => 'test-document.txt',
                        'contentType' => 'text/plain',
                        'content' => base64_encode('Hello World from attachment!'),
                    ]
                ]
            ]
        ];

        $response = $this->postJson(route('api.webhooks.resend-inbound'), $payload);
        $response->assertStatus(201);

        $ticket = SupportTicket::where('sender_email', 'attachment.user@example.com')->first();
        $this->assertNotNull($ticket);
        $this->assertCount(1, $ticket->attachments);
        
        $attachment = $ticket->attachments[0];
        $this->assertEquals('test-document.txt', $attachment['name']);
        $this->assertEquals('text/plain', $attachment['content_type']);
        $this->assertNotNull($attachment['path']);
        $this->assertNotNull($attachment['url']);

        \Illuminate\Support\Facades\Storage::assertExists($attachment['path']);
        $this->assertEquals('Hello World from attachment!', \Illuminate\Support\Facades\Storage::get($attachment['path']));
    }

    /**
     * Test that signature verification fails if header signature is invalid when secret is set.
     */
    public function test_webhook_fails_with_invalid_signature_when_secret_configured(): void
    {
        putenv('RESEND_WEBHOOK_SECRET=whsec_dGVzdF9zZWNyZXRfa2V5XzEyMzQ1Njc4OTA=');

        $payload = [
            'type' => 'email.received',
            'data' => [
                'from' => 'attacker@example.com',
                'subject' => 'Spam',
                'text' => 'Spam content',
            ]
        ];

        $response = $this->postJson(route('api.webhooks.resend-inbound'), $payload, [
            'svix-id' => 'msg_123',
            'svix-timestamp' => time(),
            'svix-signature' => 'v1,invalid_signature',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('support_tickets', ['sender_email' => 'attacker@example.com']);

        putenv('RESEND_WEBHOOK_SECRET=');
    }

    /**
     * Test that webhook succeeds when valid Svix signature is provided.
     */
    public function test_webhook_succeeds_with_valid_signature(): void
    {
        $secret = 'whsec_dGVzdF5zZWNyZXQ=';
        putenv('RESEND_WEBHOOK_SECRET=' . $secret);

        $payloadData = [
            'type' => 'email.received',
            'data' => [
                'from' => 'valid@example.com',
                'subject' => 'Signed message',
                'text' => 'Good content',
            ]
        ];
        
        $rawPayload = json_encode($payloadData);
        $svixId = 'msg_xyz';
        $svixTimestamp = time();

        $secretKey = str_replace('whsec_', '', $secret);
        $secretDecoded = base64_decode($secretKey);
        
        $signPayload = $svixId . '.' . $svixTimestamp . '.' . $rawPayload;
        $expectedSignature = 'v1,' . hash_hmac('sha256', $signPayload, $secretDecoded);

        $response = $this->postJson(route('api.webhooks.resend-inbound'), $payloadData, [
            'svix-id' => $svixId,
            'svix-timestamp' => $svixTimestamp,
            'svix-signature' => $expectedSignature,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('support_tickets', ['sender_email' => 'valid@example.com']);

        putenv('RESEND_WEBHOOK_SECRET=');
    }


    /**
     * Test webhook classification for complaints.
     */
    public function test_inbound_webhook_categorizes_complaints(): void
    {
        $payload = [
            'type' => 'email.received',
            'data' => [
                'from' => 'angry@example.com',
                'subject' => 'URGENT: abuse report',
                'text' => 'This is bad!',
                'html' => '<p>This is bad!</p>',
            ]
        ];

        $response = $this->postJson(route('api.webhooks.resend-inbound'), $payload);
        $response->assertStatus(201);

        $this->assertDatabaseHas('support_tickets', [
            'sender_email' => 'angry@example.com',
            'sender_name' => null,
            'type' => 'complaint',
        ]);
    }

    /**
     * Test webhook ignores events other than email.received.
     */
    public function test_inbound_webhook_ignores_other_events(): void
    {
        $payload = [
            'type' => 'email.sent',
            'data' => [
                'from' => 'support@devsense.work',
            ]
        ];

        $response = $this->postJson(route('api.webhooks.resend-inbound'), $payload);
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Ignored event']);

        $this->assertDatabaseEmpty('support_tickets');
    }

    /**
     * Test guests cannot access tickets.
     */
    public function test_guests_cannot_access_tickets(): void
    {
        $this->get(route('admin.tickets.index', ['locale' => 'en']))->assertRedirect('/login');
    }

    /**
     * Test readers cannot access tickets.
     */
    public function test_readers_cannot_access_tickets(): void
    {
        $reader = User::factory()->create(['role' => User::ROLE_READER]);
        $this->actingAs($reader);

        $this->get(route('admin.tickets.index', ['locale' => 'en']))->assertStatus(403);
    }

    /**
     * Test authors cannot access tickets.
     */
    public function test_authors_cannot_access_tickets(): void
    {
        $author = User::factory()->author()->create();
        $this->actingAs($author);

        $this->get(route('admin.tickets.index', ['locale' => 'en']))->assertStatus(403);
    }

    /**
     * Test super admin can access tickets index.
     */
    public function test_super_admin_can_access_tickets(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = SupportTicket::create([
            'sender_email' => 'user@example.com',
            'subject' => 'Help me please',
            'message' => 'I cannot login to my account.',
            'type' => 'general',
            'status' => 'open',
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('admin.tickets.index', ['locale' => 'en']));
        $response->assertStatus(200);
        $response->assertSee('Help me please');
        $response->assertSee('user@example.com');
    }

    /**
     * Test super admin can view ticket details.
     */
    public function test_super_admin_can_view_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = SupportTicket::create([
            'sender_email' => 'user@example.com',
            'subject' => 'Help me please',
            'message' => 'I cannot login to my account.',
            'type' => 'general',
            'status' => 'open',
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('admin.tickets.show', ['locale' => 'en', 'ticket' => $ticket->id]));
        $response->assertStatus(200);
        $response->assertSee('I cannot login to my account.');
    }

    /**
     * Test super admin can submit a reply to a ticket.
     */
    public function test_super_admin_can_reply_to_ticket(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $ticket = SupportTicket::create([
            'sender_email' => 'user@example.com',
            'subject' => 'Help me please',
            'message' => 'I cannot login to my account.',
            'type' => 'general',
            'status' => 'open',
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('admin.tickets.reply', ['locale' => 'en', 'ticket' => $ticket->id]), [
            'reply_message' => 'We have resolved your login issue.',
        ]);

        $response->assertRedirect(route('admin.tickets.index', ['locale' => 'en']));
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('answered', $ticket->status);
        $this->assertEquals('We have resolved your login issue.', $ticket->reply_message);
        $this->assertNotNull($ticket->replied_at);

        Mail::assertSent(SupportTicketReplyMail::class, function ($mail) use ($ticket) {
            return $mail->hasTo($ticket->sender_email) &&
                   $mail->ticket->id === $ticket->id;
        });
    }
}
