<x-layout 
    title="{{ __('ui.suggestions.title') }}"
    description="Suggest guides and site features for the DevSense platform."
>
<div class="admin-container" style="max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem; font-family: 'Inter', sans-serif;">
    
    <!-- Header Section -->
    <div style="text-align: center; margin-bottom: 3rem;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.75rem; font-weight: 800; background: linear-gradient(135deg, #fff, #93c5fd, #a5b4fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0 0 1rem;">
            {{ __('ui.suggestions.hero_title') }}
        </h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 600px; margin: 0 auto; line-height: 1.6;">
            {{ __('ui.suggestions.hero_lead') }}
        </p>
    </div>

    @if (session('success'))
        <div class="admin-alert admin-alert--success" style="margin-bottom: 2rem;">
            {{ session('success') }}
        </div>
    @endif

    <!-- Submit Suggestion Form Card -->
    <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; margin-bottom: 3rem; backdrop-filter: blur(10px);">
        <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 700; color: var(--text-color); margin-top: 0; margin-bottom: 1rem;">
            {{ __('ui.suggestions.submit_title') }}
        </h2>
        @auth
            <form action="{{ route('suggestions.storeGeneral', ['locale' => app()->getLocale()]) }}" method="POST">
                @csrf
                <div class="form-group" style="margin-bottom: 1rem;">
                    <textarea name="content" rows="3" required class="form-input" style="background: var(--page-bg); border: 1px solid var(--border-color); color: var(--text-color); width: 100%; border-radius: 0.5rem; padding: 0.75rem; font-family: inherit; font-size: 0.95rem; resize: vertical;" placeholder="{{ __('ui.suggestions.placeholder') }}"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="admin-btn admin-btn--primary" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); color: white; border: none; font-weight: 600; padding: 0.6rem 1.5rem; border-radius: 0.5rem; cursor: pointer;">
                        {{ __('ui.suggestions.submit_btn') }}
                    </button>
                </div>
            </form>
        @else
            <div style="text-align: center; padding: 1rem 0;">
                <p style="margin: 0 0 1rem 0; color: var(--text-muted); font-size: 0.95rem;">
                    {{ __('ui.suggestions.sign_in_hint') }}
                </p>
                <a href="{{ route('login.locale') }}" class="btn-primary glow-button" style="text-decoration: none; padding: 0.5rem 1.5rem; border-radius: 0.5rem; display: inline-block; font-weight: 600;">
                    {{ __('ui.nav.login') }}
                </a>
            </div>
        @endauth
    </div>

    <!-- Suggestions List -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        @if($suggestions->isEmpty())
            <div style="text-align: center; padding: 3rem; border: 1px dashed var(--border-color); border-radius: 16px; color: var(--text-muted);">
                {{ __('ui.suggestions.empty') }}
            </div>
        @else
            @foreach($suggestions as $suggestion)
                @php
                    $voted = $suggestion->votedBy(auth()->user());
                @endphp
                <div class="suggestion-card" data-suggestion-id="{{ $suggestion->id }}" style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; transition: border-color 0.25s ease;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                                <span style="font-weight: 600; color: var(--text-color);">{{ $suggestion->user?->name ?? 'Guest' }}</span>
                                <span>•</span>
                                <span>{{ $suggestion->created_at->diffForHumans() }}</span>
                                @if($suggestion->article->id)
                                    <span>•</span>
                                    <span style="background: rgba(99, 102, 241, 0.1); color: var(--primary-color); padding: 0.15rem 0.4rem; border-radius: 0.25rem;">
                                        {{ __('ui.suggestions.for_guide') }} {{ $suggestion->article->translate()?->title }}
                                    </span>
                                @endif
                            </div>
                            <p style="color: var(--text-color); font-size: 1rem; line-height: 1.5; margin: 0; white-space: pre-line;">
                                {{ $suggestion->content }}
                            </p>
                        </div>

                        <!-- Vote Action -->
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem;">
                            @auth
                                <button type="button" onclick="toggleSuggestionVote({{ $suggestion->id }})" class="vote-btn {{ $voted ? 'voted' : '' }}" style="background: {{ $voted ? 'var(--primary-color)' : 'rgba(255, 255, 255, 0.05)' }}; border: none; border-radius: 8px; width: 45px; height: 45px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; color: {{ $voted ? 'white' : 'var(--text-color)' }}; transition: all 0.2s;">
                                    <span style="font-size: 1rem; font-weight: 700;">▲</span>
                                    <span class="vote-count" style="font-size: 0.75rem; font-weight: 700; margin-top: -2px;">{{ $suggestion->votes_count }}</span>
                                </button>
                            @else
                                <a href="{{ route('login.locale') }}" style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); border-radius: 8px; width: 45px; height: 45px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; color: var(--text-muted);">
                                    <span style="font-size: 1rem;">▲</span>
                                    <span style="font-size: 0.75rem; font-weight: 700; margin-top: -2px;">{{ $suggestion->votes_count }}</span>
                                </a>
                            @endauth
                        </div>
                    </div>

                    <!-- Comments / Supplement Section -->
                    <div style="border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 1rem;">
                        <button onclick="toggleComments({{ $suggestion->id }})" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.85rem; padding: 0; display: inline-flex; align-items: center; gap: 0.25rem;">
                            💬 <span>{{ $suggestion->comments()->count() }} {{ __('ui.suggestions.comments') }}</span>
                        </button>

                        <div id="comments-container-{{ $suggestion->id }}" style="display: none; margin-top: 1rem; flex-direction: column; gap: 0.75rem; padding-left: 1rem; border-left: 2px solid var(--border-color);">
                            <!-- Existing Comments -->
                            @foreach($suggestion->comments as $comment)
                                <div style="font-size: 0.85rem; background: rgba(0,0,0,0.1); padding: 0.75rem; border-radius: 0.5rem; border: 1px solid var(--border-color);">
                                    <div style="display: flex; justify-content: space-between; color: var(--text-muted); margin-bottom: 0.25rem; font-size: 0.75rem;">
                                        <span style="font-weight: 600; color: var(--text-color);">{{ $comment->user?->name ?? 'Guest' }}</span>
                                        <span>{{ $comment->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p style="margin: 0; color: var(--text-color); line-height: 1.4;">{{ $comment->content }}</p>
                                </div>
                            @endforeach

                            <!-- Add Comment Form -->
                            @auth
                                <form action="{{ route('suggestions.comments.store', ['suggestion' => $suggestion->id, 'locale' => app()->getLocale()]) }}" method="POST" style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                                    @csrf
                                    <input type="text" name="content" required placeholder="{{ __('ui.suggestions.add_comment_placeholder') }}" class="form-input" style="flex-grow: 1; padding: 0.4rem 0.75rem; font-size: 0.85rem; border-radius: 0.375rem; background: var(--page-bg); border: 1px solid var(--border-color); color: var(--text-color);">
                                    <button type="submit" class="admin-btn admin-btn--primary" style="padding: 0.4rem 1rem; font-size: 0.85rem; border-radius: 0.375rem; cursor: pointer;">
                                        {{ __('ui.suggestions.post') }}
                                    </button>
                                </form>
                            @endauth
                        </div>
                    </div>
                </div>
            @endforeach

            <div style="margin-top: 2rem;">
                {{ $suggestions->links('partials.pagination') }}
            </div>
        @endif
    </div>

</div>

<script>
function toggleSuggestionVote(id) {
    const card = document.querySelector(`.suggestion-card[data-suggestion-id="${id}"]`);
    const btn = card.querySelector('.vote-btn');
    const countSpan = btn.querySelector('.vote-count');
    
    btn.disabled = true;
    
    fetch(`/${document.documentElement.lang}/suggestions/${id}/vote`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            if (data.voted) {
                btn.classList.add('voted');
                btn.style.background = 'var(--primary-color)';
                btn.style.color = 'white';
            } else {
                btn.classList.remove('voted');
                btn.style.background = 'rgba(255, 255, 255, 0.05)';
                btn.style.color = 'var(--text-color)';
            }
            countSpan.innerText = data.count;
        }
    })
    .catch(err => {
        btn.disabled = false;
        console.error('Error voting:', err);
    });
}

function toggleComments(id) {
    const container = document.getElementById(`comments-container-${id}`);
    if (container.style.display === 'none') {
        container.style.display = 'flex';
    } else {
        container.style.display = 'none';
    }
}
</script>
</x-layout>
