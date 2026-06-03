@php
    $locale = app()->getLocale();
@endphp

<div class="suggestions-section" id="suggestions-block" style="margin-top: 3rem; padding: 2rem; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: 1rem; backdrop-filter: blur(10px);">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; margin: 0; color: var(--text-color); font-family: 'Outfit', sans-serif;">{{ __('ui.suggestions.title') }}</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.25rem 0 0 0;">{{ __('ui.suggestions.subtitle') }}</p>
        </div>
    </div>

    <!-- Suggestions List -->
    <div class="suggestions-list" style="display: flex; flex-direction: column; gap: 1.5rem;">
        @forelse($suggestions as $suggestion)
            <div class="suggestion-card" id="suggestion-{{ $suggestion->id }}" style="padding: 1.5rem; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); border-radius: 0.75rem; transition: transform 0.2s ease, border-color 0.2s ease;" onmouseover="this.style.borderColor='var(--primary-color)'" onmouseout="this.style.borderColor='var(--border-color)'">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem;">
                    <!-- User Info & Date -->
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 2.25rem; height: 2.25rem; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.9rem; border: 1px solid var(--border-color);">
                            {{ strtoupper(substr($suggestion->user->name ?? 'U', 0, 2)) }}
                        </div>
                        <div>
                            <span style="font-weight: 600; font-size: 0.95rem; color: var(--text-color); display: block;">{{ $suggestion->user->name }}</span>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">{{ $suggestion->created_at->diffForHumans() }}</span>
                        </div>
                    </div>

                    <!-- Status Badges -->
                    <div>
                        @php
                            $statusColors = [
                                'pending' => ['bg' => 'rgba(234, 179, 8, 0.15)', 'text' => '#eab308'],
                                'approved' => ['bg' => 'rgba(59, 130, 246, 0.15)', 'text' => '#3b82f6'],
                                'implemented' => ['bg' => 'rgba(16, 185, 129, 0.15)', 'text' => '#10b981'],
                                'rejected' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'text' => '#ef4444']
                            ];
                            $colors = $statusColors[$suggestion->status] ?? $statusColors['pending'];
                        @endphp
                        <span style="padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; background: {{ $colors['bg'] }}; color: {{ $colors['text'] }}; border: 1px solid {{ $colors['text'] }}33;">
                            {{ __('ui.suggestions.status.' . $suggestion->status) }}
                        </span>
                    </div>
                </div>

                <!-- Suggestion Content -->
                <p style="color: var(--text-color); font-size: 1rem; line-height: 1.6; margin: 0 0 1.25rem 0; white-space: pre-line;">{{ $suggestion->content }}</p>

                <!-- Footer Actions: Vote & Comments Toggle -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 1rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <!-- Vote button (AJAX) -->
                        <button onclick="toggleSuggestionVote({{ $suggestion->id }}, this)" 
                                data-voted="{{ $suggestion->votedBy(auth()->user()) ? 'true' : 'false' }}"
                                style="display: flex; align-items: center; gap: 0.5rem; background: {{ $suggestion->votedBy(auth()->user()) ? 'rgba(99, 102, 241, 0.2)' : 'transparent' }}; border: 1px solid var(--border-color); color: {{ $suggestion->votedBy(auth()->user()) ? 'var(--primary-color)' : 'var(--text-color)' }}; padding: 0.4rem 0.8rem; border-radius: 0.375rem; cursor: pointer; font-size: 0.87rem; font-weight: 500; transition: all 0.2s ease;">
                            <svg viewBox="0 0 24 24" fill="{{ $suggestion->votedBy(auth()->user()) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
                            </svg>
                            <span class="vote-text">{{ $suggestion->votedBy(auth()->user()) ? __('ui.suggestions.upvoted_btn') : __('ui.suggestions.upvote_btn') }}</span>
                            <span style="font-weight: 600;" class="vote-count">({{ $suggestion->votes->count() }})</span>
                        </button>

                        <!-- Comments toggle -->
                        <span style="color: var(--text-muted); font-size: 0.87rem;">
                            {{ trans_choice('ui.suggestions.comments_count', $suggestion->comments->count(), ['count' => $suggestion->comments->count()]) }}
                        </span>
                    </div>
                </div>

                <!-- Comments/Supplement Feed inside Suggestion Card -->
                <div class="comments-section" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed rgba(255, 255, 255, 0.05);">
                    <h4 style="font-size: 0.9rem; font-weight: 600; color: var(--text-color); margin: 0 0 1rem 0; text-transform: uppercase; tracking: 0.05em;">{{ __('ui.suggestions.comments_title') }}</h4>
                    
                    <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem;">
                        @foreach($suggestion->comments as $comment)
                            <div style="background: rgba(255, 255, 255, 0.01); border: 1px solid rgba(255, 255, 255, 0.03); border-radius: 0.5rem; padding: 0.75rem 1rem; position: relative;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                    <span style="font-weight: 600; font-size: 0.85rem; color: var(--text-color);">{{ $comment->user->name }}</span>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="font-size: 0.75rem; color: var(--text-muted);">{{ $comment->created_at->diffForHumans() }}</span>
                                        <!-- Report Comment button -->
                                        <button onclick="openReportModal('App\\Models\\ArticleSuggestionComment', {{ $comment->id }})" title="Report comment" style="background: transparent; border: none; color: var(--text-muted); cursor: pointer; padding: 0.1rem; display: flex; align-items: center;">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12" style="opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">
                                                <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                                                <line x1="4" y1="22" x2="4" y2="15"></line>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <p style="color: var(--text-color); font-size: 0.875rem; margin: 0; line-height: 1.5;">{{ $comment->content }}</p>
                            </div>
                        @endforeach
                    </div>

                    <!-- Add Comment Form -->
                    @auth
                        <form action="{{ route('suggestions.comments.store', ['locale' => $locale, 'suggestion' => $suggestion->id]) }}" method="POST" style="display: flex; gap: 0.5rem;">
                            @csrf
                            <input type="text" name="content" placeholder="{{ __('ui.suggestions.add_comment_placeholder') }}" required style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: var(--text-color); border-radius: 0.375rem; padding: 0.4rem 0.75rem; flex-grow: 1; font-size: 0.875rem; font-family: inherit;">
                            <button type="submit" class="admin-btn admin-btn--primary" style="padding: 0.4rem 1rem; font-size: 0.875rem;">
                                {{ __('ui.suggestions.submit_comment') }}
                            </button>
                        </form>
                    @endauth
                </div>
            </div>
        @empty
            <div style="text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.95rem;">
                {{ __('ui.suggestions.no_suggestions') }}
            </div>
        @endforelse
    </div>

    <!-- Submit Suggestion Form / Cabinet CTA -->
    <div style="margin-top: 2.5rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
        @auth
            <h3 style="font-size: 1.2rem; font-weight: 600; color: var(--text-color); margin: 0 0 1rem 0; font-family: 'Outfit', sans-serif;">{{ __('ui.suggestions.add_btn') }}</h3>
            <form action="{{ route('suggestions.store', ['locale' => $locale, 'article' => $article->id]) }}" method="POST">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <textarea name="content" rows="4" placeholder="{{ __('ui.suggestions.placeholder') }}" required style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: var(--text-color); width: 100%; border-radius: 0.5rem; padding: 0.75rem; font-size: 0.95rem; font-family: inherit; line-height: 1.5; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='var(--border-color)'"></textarea>
                    
                    @if ($errors->any())
                        <div style="color: #ef4444; font-size: 0.875rem;">
                            @foreach ($errors->all() as $error)
                                <p style="margin: 0;">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif
                    
                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="admin-btn admin-btn--primary" style="padding: 0.6rem 1.5rem; font-size: 0.95rem; font-weight: 600;">
                            {{ __('ui.suggestions.add_btn') }}
                        </button>
                    </div>
                </div>
            </form>
        @else
            <div style="text-align: center; padding: 1.5rem; background: rgba(255, 255, 255, 0.01); border: 1px dashed var(--border-color); border-radius: 0.5rem;">
                <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0 0 1rem 0;">{{ __('ui.suggestions.login_required') }}</p>
                <a href="{{ route('login.locale', ['locale' => $locale]) }}" class="admin-btn admin-btn--primary" style="display: inline-block; padding: 0.5rem 1.25rem; font-size: 0.9rem; text-decoration: none;">
                    {{ __('ui.nav.login') }}
                </a>
            </div>
        @endauth
    </div>
</div>

<script>
function toggleSuggestionVote(suggestionId, button) {
    const isVoted = button.getAttribute('data-voted') === 'true';
    const voteTextSpan = button.querySelector('.vote-text');
    const voteCountSpan = button.querySelector('.vote-count');
    const csrfToken = document.querySelector('input[name="_token"]').value;

    button.disabled = true;

    fetch('/' + document.documentElement.lang + '/suggestions/' + suggestionId + '/vote', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => {
        if (response.status === 401) {
            window.location.href = '/' + document.documentElement.lang + '/login';
            return;
        }
        return response.json();
    })
    .then(data => {
        button.disabled = false;
        if (data && data.success) {
            button.setAttribute('data-voted', data.voted ? 'true' : 'false');
            if (data.voted) {
                button.style.background = 'rgba(99, 102, 241, 0.2)';
                button.style.color = 'var(--primary-color)';
                button.querySelector('svg').setAttribute('fill', 'currentColor');
                voteTextSpan.innerText = "{{ __('ui.suggestions.upvoted_btn') }}";
            } else {
                button.style.background = 'transparent';
                button.style.color = 'var(--text-color)';
                button.querySelector('svg').setAttribute('fill', 'none');
                voteTextSpan.innerText = "{{ __('ui.suggestions.upvote_btn') }}";
            }
            voteCountSpan.innerText = '(' + data.count + ')';
        }
    })
    .catch(err => {
        button.disabled = false;
        console.error('Error voting:', err);
    });
}
</script>
