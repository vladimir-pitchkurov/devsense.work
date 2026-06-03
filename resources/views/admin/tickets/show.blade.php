<x-layout title="Admin - View Ticket #{{ $ticket->id }} | DevSense" description="Reply to user support ticket">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">Ticket #{{ $ticket->id }}</h1>
        <div class="header-actions">
            <a href="{{ route('admin.tickets.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Back to Tickets
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="admin-alert admin-alert--danger" style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); color: #ef4444; border-radius: 0.5rem; padding: 0.75rem 1rem; font-size: 0.875rem; margin-bottom: 1.5rem;">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Ticket Information Card -->
        <div class="admin-card" style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;">
                <div>
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-color); margin: 0 0 0.25rem;">
                        {{ $ticket->subject }}
                    </h2>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                        From: <strong>{{ $ticket->sender_name ?: 'Guest' }}</strong> &lt;{{ $ticket->sender_email }}&gt;
                    </p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    @if ($ticket->type === 'complaint')
                        <span style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-size: 0.8rem; font-weight: 600;">Complaint</span>
                    @elseif ($ticket->type === 'suggestion')
                        <span style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-size: 0.8rem; font-weight: 600;">Suggestion</span>
                    @else
                        <span style="background: rgba(100, 116, 139, 0.1); color: var(--text-muted); border: 1px solid rgba(100, 116, 139, 0.2); padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-size: 0.8rem; font-weight: 600;">General</span>
                    @endif

                    @if ($ticket->status === 'open')
                        <span style="background: rgba(249, 115, 22, 0.1); color: #f97316; border: 1px solid rgba(249, 115, 22, 0.2); padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-size: 0.8rem; font-weight: 600;">Open</span>
                    @else
                        <span style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-size: 0.8rem; font-weight: 600;">Answered</span>
                    @endif
                </div>
            </div>

            <div style="font-size: 0.95rem; color: var(--text-color); white-space: pre-wrap; line-height: 1.7; background: var(--page-bg); padding: 1.25rem; border-radius: 0.5rem; border: 1px solid var(--border-color);">{{ $ticket->message }}</div>
            
            @if ($ticket->attachments && count($ticket->attachments) > 0)
                <div style="margin-top: 1.5rem; border-top: 1px dashed var(--border-color); padding-top: 1rem;">
                    <h4 style="font-family: 'Outfit', sans-serif; font-size: 0.9rem; font-weight: 700; color: var(--text-color); margin: 0 0 0.75rem;">
                        Attachments ({{ count($ticket->attachments) }})
                    </h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        @foreach ($ticket->attachments as $attachment)
                            <a href="{{ $attachment['url'] }}" target="_blank" download class="admin-btn admin-btn--secondary" style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; padding: 0.4rem 0.75rem; text-decoration: none;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="flex-shrink: 0; color: var(--text-muted);">
                                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                </svg>
                                <span style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ $attachment['name'] }}
                                </span>
                                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">
                                    ({{ strtoupper(explode('/', $attachment['content_type'])[1] ?? 'FILE') }})
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div style="font-size: 0.8rem; color: var(--text-muted); text-align: right; margin-top: 0.5rem;">
                Received: {{ $ticket->created_at->format('Y-m-d H:i:s') }}
            </div>
        </div>

        @if ($ticket->status === 'answered')
            <!-- Admin Reply Display -->
            <div class="admin-card" style="padding: 1.5rem; border-left: 4px solid #22c55e;">
                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 700; color: #22c55e; margin: 0 0 1rem;">
                    Admin Reply Sent
                </h3>
                <div style="font-size: 0.95rem; color: var(--text-color); white-space: pre-wrap; line-height: 1.7; background: var(--page-bg); padding: 1.25rem; border-radius: 0.5rem; border: 1px solid var(--border-color);">{{ $ticket->reply_message }}</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); text-align: right; margin-top: 0.5rem;">
                    Replied at: {{ $ticket->replied_at->format('Y-m-d H:i:s') }}
                </div>
            </div>
        @else
            <!-- Reply Form -->
            <div class="admin-card" style="padding: 1.5rem;">
                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 700; color: var(--text-color); margin: 0 0 1rem;">
                    Send Reply
                </h3>

                <form action="{{ route('admin.tickets.reply', ['locale' => app()->getLocale(), 'ticket' => $ticket]) }}" method="POST">
                    @csrf
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.5rem;">
                        <label for="reply_message" style="font-size: 0.75rem; font-weight: 700; color: var(--text-color); text-transform: uppercase;">Message</label>
                        <textarea
                            name="reply_message"
                            id="reply_message"
                            rows="10"
                            required
                            placeholder="Type your response here..."
                            style="width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1.5px solid var(--border-color); background: var(--page-bg); color: var(--text-color); font-family: inherit; font-size: 0.95rem; line-height: 1.5;"
                        >{{ old('reply_message') }}</textarea>
                    </div>

                    <button type="submit" class="admin-btn admin-btn--primary" style="padding: 0.75rem 1.5rem;">
                        Send Reply & Close Ticket
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
</x-layout>
