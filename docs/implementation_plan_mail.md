# Cloudflare Migration, Resend Inbound & Support Tickets Setup

This plan details:
1. **Infrastructure**: Transferring DNS from GoDaddy to Cloudflare and configuring `mail.devsense.work`.
2. **Outbound Sender**: Configuring the default system mailer to use `noreply@mail.devsense.work`.
3. **Inbound Webhook**: Creating a webhook to parse incoming emails via Resend.
4. **Admin Cabinet**: Implementing a Support Ticket listing and reply system.

---

## User Action Required: Cloudflare DNS Migration

To migrate your site to Cloudflare and preserve your existing Nginx + Certbot setup:

1. **Add Domain to Cloudflare**:
   - Log in to Cloudflare, click **Add a Site**, and enter `devsense.work`.
   - Select the Free plan. Cloudflare will automatically scan your existing DNS records from GoDaddy.

2. **Verify/Import DNS Records**:
   - Ensure the `@` (root) and `dev` subdomains are imported as `A` records pointing to your server's IP address.
   - For now, set the proxy status to **DNS Only** (grey cloud) to ensure your Nginx Certbot SSL renewals continue without issues, or set to **Proxied** (orange cloud) and ensure SSL setting in Cloudflare is set to **Full (Strict)**.

3. **Update Nameservers in GoDaddy**:
   - Go to GoDaddy DNS Management for `devsense.work`.
   - Change nameservers to the two Cloudflare nameservers provided during setup.
   - *Note: DNS propagation can take 2-24 hours, but usually updates within 15 minutes.*

4. **Verify SSL Configuration in Cloudflare**:
   - In Cloudflare, go to **SSL/TLS -> Overview**.
   - Select **Full (Strict)**. This ensures that Cloudflare encrypts traffic to the visitor, and connects securely to your Nginx server using the existing Certbot Let's Encrypt certificate.

---

## User Action Required: Configure Resend Inbound Email

1. **Add `mail.devsense.work` to Resend**:
   - In Resend, go to **Domains -> Add Domain** and add `mail.devsense.work`.
   - Configure the required SPF, DKIM, and MX records in Cloudflare (Resend will provide MX records for inbound routing pointing to `inbound-smtp.us-east-1.amazonaws.com`).
2. **Set up Inbound Webhook**:
   - In Resend, go to **Webhooks -> Add Webhook**.
   - Set the Payload URL to `https://devsense.work/api/webhooks/resend-inbound`.
   - Select the event `email.received`.

---

## Proposed Changes

### 1. Database Model and Migration
#### [NEW] [create_support_tickets_table.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/database/migrations/2026_06_03_000000_create_support_tickets_table.php)
- Create `support_tickets` table:
  - `sender_email` (string)
  - `sender_name` (string, nullable)
  - `subject` (string)
  - `message` (text)
  - `type` (string: complaint, suggestion, general)
  - `status` (string: open, answered)
  - `reply_message` (text, nullable)
  - `replied_at` (timestamp, nullable)

#### [NEW] [SupportTicket.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/app/Models/SupportTicket.php)
- Define properties, fillable fields, and scopes for open/closed tickets.

### 2. Inbound Webhook Handler
#### [NEW] [ResendInboundController.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/app/Http/Controllers/Api/Webhook/ResendInboundController.php)
- Parse `data.from` to extract sender name and email.
- Categorize email subject/body into types (`complaint`, `suggestion`, `general`).
- Insert records into `support_tickets`.

#### [MODIFY] [api.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/routes/api.php)
- Add `/webhooks/resend-inbound` route bypassing authentication.

### 3. Outbound Mailable for Support Replies
#### [NEW] [SupportTicketReplyMail.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/app/Mail/SupportTicketReplyMail.php)
- Mailable class that accepts the ticket record and formats the admin's reply.

#### [NEW] [reply.blade.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/resources/views/emails/support-ticket-reply.blade.php)
- Sleek markdown/HTML email template for sending replies to users.

### 4. Admin Management Cabinet
#### [NEW] [AdminTicketsController.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/app/Http/Controllers/Admin/AdminTicketsController.php)
- Implement `index()` listing tickets (with tabs for all, open, answered).
- Implement `show(SupportTicket $ticket)` showing the ticket message and a reply form.
- Implement `reply(Request $request, SupportTicket $ticket)` sending the mailable and marking the ticket as `answered`.

#### [NEW] [index.blade.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/resources/views/admin/tickets/index.blade.php)
- Render list of support tickets with status and type badges.

#### [NEW] [show.blade.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/resources/views/admin/tickets/show.blade.php)
- Display ticket details and rich-styled form to submit replies.

#### [MODIFY] [web.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/routes/web.php)
- Register tickets routes inside the localized admin routing group.
- Add navigation item in the sidebar of the admin layouts.

#### [MODIFY] [.env.example](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/.env.example)
- Update `MAIL_FROM_ADDRESS` default value to `noreply@mail.devsense.work`.

## Verification Plan

### Automated Tests
- Create `Tests\Feature\SupportTicketsTest.php` to verify:
  1. Inbound webhook endpoint parses payload and saves support tickets.
  2. Unauthenticated requests to webhook are allowed.
  3. Support ticket listing and show views load successfully in admin dashboard.
  4. Replying to a ticket updates its database state and dispatches the outgoing mailable.
- Run tests: `wsl ./vendor/bin/sail test`.
