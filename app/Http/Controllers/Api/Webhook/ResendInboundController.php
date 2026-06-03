<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResendInboundController extends Controller
{
    /**
     * Handle the incoming Resend Inbound Email webhook.
     */
    public function handle(Request $request)
    {
        Log::info('Resend Inbound Webhook payload received', ['payload' => $request->all()]);

        // Verify Svix Webhook Signature if secret is configured
        $svixId = $request->header('svix-id');
        $svixTimestamp = $request->header('svix-timestamp');
        $svixSignature = $request->header('svix-signature');
        $secret = env('RESEND_WEBHOOK_SECRET');

        if (!empty($secret)) {
            if (!$svixId || !$svixTimestamp || !$svixSignature) {
                Log::warning('Resend Webhook validation failed: missing Svix headers');
                return response()->json(['message' => 'Unauthorized: Missing headers'], 401);
            }

            // Verify timestamp is within 5 minutes
            if (abs(time() - (int)$svixTimestamp) > 300) {
                Log::warning('Resend Webhook validation failed: expired timestamp', ['timestamp' => $svixTimestamp]);
                return response()->json(['message' => 'Unauthorized: Expired signature'], 401);
            }

            $secretKey = str_replace('whsec_', '', $secret);
            $secretDecoded = base64_decode($secretKey);

            $payload = $svixId . '.' . $svixTimestamp . '.' . $request->getContent();
            $expectedSignature = 'v1,' . hash_hmac('sha256', $payload, $secretDecoded);

            $signatures = explode(' ', $svixSignature);
            $signatureValid = false;
            foreach ($signatures as $sig) {
                if (hash_equals($sig, $expectedSignature)) {
                    $signatureValid = true;
                    break;
                }
            }

            if (!$signatureValid) {
                Log::warning('Resend Webhook validation failed: invalid signature');
                return response()->json(['message' => 'Unauthorized: Invalid signature'], 401);
            }
        }

        if ($request->input('type') !== 'email.received') {
            return response()->json(['message' => 'Ignored event'], 200);
        }

        $from = $request->input('data.from');
        if (empty($from)) {
            return response()->json(['message' => 'Missing sender info'], 400);
        }

        // Parse "Name <email@domain.com>" or just "email@domain.com"
        $name = null;
        $email = $from;
        if (preg_match('/^(.*?)\s*<(.*?)>$/', $from, $matches)) {
            $name = trim($matches[1], ' "');
            $email = trim($matches[2]);
        }

        $subject = $request->input('data.subject') ?: '(No Subject)';
        $message = $request->input('data.text') ?: strip_tags($request->input('data.html') ?: '');

        // Basic classification based on content/subject
        $type = 'general';
        $subjectLower = mb_strtolower($subject);
        $messageLower = mb_strtolower($message);

        if (
            str_contains($subjectLower, 'complaint') || 
            str_contains($subjectLower, 'жалоба') || 
            str_contains($subjectLower, 'abuse') || 
            str_contains($messageLower, 'жалоба') || 
            str_contains($messageLower, 'abuse')
        ) {
            $type = 'complaint';
        } elseif (
            str_contains($subjectLower, 'suggestion') || 
            str_contains($subjectLower, 'предложение') || 
            str_contains($subjectLower, 'feedback') || 
            str_contains($subjectLower, 'отзыв') ||
            str_contains($messageLower, 'предложение') || 
            str_contains($messageLower, 'suggestion') ||
            str_contains($messageLower, 'отзыв')
        ) {
            $type = 'suggestion';
        }

        // Parse and decode attachments if they exist
        $attachmentsData = [];
        $attachments = $request->input('data.attachments');
        if (is_array($attachments)) {
            foreach ($attachments as $attachment) {
                $fileName = $attachment['name'] ?? 'file';
                $contentType = $attachment['contentType'] ?? 'application/octet-stream';
                $content = $attachment['content'] ?? '';

                if (!empty($content)) {
                    $decodedContent = base64_decode($content);
                    $safeBaseName = pathinfo($fileName, PATHINFO_FILENAME);
                    $safeBaseName = Str::slug($safeBaseName);
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    
                    $uniquePath = 'support_attachments/' . uniqid('att_', true) . '_' . $safeBaseName . ($extension ? '.' . $extension : '');

                    Storage::put($uniquePath, $decodedContent);

                    $attachmentsData[] = [
                        'name'         => $fileName,
                        'content_type' => $contentType,
                        'path'         => $uniquePath,
                        'url'          => Storage::url($uniquePath),
                    ];
                }
            }
        }

        $ticket = SupportTicket::create([
            'sender_email' => $email,
            'sender_name'  => $name,
            'subject'      => $subject,
            'message'      => $message,
            'attachments'  => $attachmentsData !== [] ? $attachmentsData : null,
            'type'         => $type,
            'status'       => 'open',
        ]);

        return response()->json([
            'message'   => 'Ticket created successfully',
            'ticket_id' => $ticket->id
        ], 201);
    }
}

