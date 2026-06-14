<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('emails.new_comment.subject') }}</title>
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
        .content {
            margin-bottom: 32px;
        }
        .quote {
            border-left: 4px solid #cbd5e1;
            padding-left: 16px;
            color: #475569;
            font-style: italic;
            margin: 16px 0;
            white-space: pre-wrap;
        }
        .btn {
            display: inline-block;
            background-color: #6366f1;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 15px;
            text-align: center;
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

        <div class="content">
            <p>Hello {{ $recipient->name }},</p>
            @if($type === 'suggestion')
                <p>{{ __('emails.new_comment.suggestion_intro', ['user' => $triggerUser->name, 'title' => $articleTranslation?->title ?? $article->slug]) }}</p>
            @else
                <p>{{ __('emails.new_comment.comment_intro', ['user' => $triggerUser->name, 'title' => $articleTranslation?->title ?? $article->slug]) }}</p>
            @endif

            <div class="quote">
                {{ $commentContent }}
            </div>

            <a href="{{ $actionUrl }}" class="btn">{{ __('emails.new_comment.action') }}</a>
        </div>

        <div class="footer">
            You are receiving this because you subscribed to notifications on DevSense. You can update your settings in your profile.
        </div>
    </div>
</body>
</html>
