<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Support Ticket Reply</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #334155;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f8fafc;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .header {
            border-bottom: 2px solid #6366f1;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .logo {
            font-size: 24px;
            font-weight: 800;
            color: #1e1b4b;
            letter-spacing: -0.5px;
        }
        .message-body {
            margin-bottom: 32px;
            white-space: pre-wrap;
        }
        .original-quote {
            border-left: 4px solid #cbd5e1;
            padding-left: 16px;
            color: #64748b;
            font-size: 0.9em;
            margin-top: 32px;
            white-space: pre-wrap;
        }
        .original-header {
            font-weight: bold;
            margin-bottom: 8px;
            color: #475569;
        }
        .footer {
            margin-top: 32px;
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
            font-size: 12px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">DevSense</div>
        </div>

        <p>Hello{{ $ticket->sender_name ? ' ' . $ticket->sender_name : '' }},</p>

        <div class="message-body">
{!! nl2br(e($ticket->reply_message)) !!}
        </div>

        <p>Best regards,<br><strong>DevSense Team</strong></p>

        <div class="original-quote">
            <div class="original-header">--- Original Message ---</div>
            <strong>From:</strong> {{ $ticket->sender_name ? $ticket->sender_name . ' <' . $ticket->sender_email . '>' : $ticket->sender_email }}<br>
            <strong>Date:</strong> {{ $ticket->created_at->format('F j, Y, g:i a') }} (UTC)<br>
            <strong>Subject:</strong> {{ $ticket->subject }}<br><br>
            {{ $ticket->message }}
        </div>

        <div class="footer">
            This is an automated reply from the DevSense Support Team. Please do not reply to this email directly unless instructed.
        </div>
    </div>
</body>
</html>
