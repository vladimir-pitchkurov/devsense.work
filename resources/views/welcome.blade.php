<x-layout :title="__('ui.nav.home')" :description="__('ui.welcome.hero_lead')">
    <div class="space-y-16 py-8" style="display: flex; flex-direction: column; gap: 4rem;">
        <!-- Hero section -->
        <section class="relative overflow-hidden rounded-3xl bg-slate-900 px-6 py-20 text-center shadow-2xl dark:bg-black/40 border border-slate-800" style="position: relative; border-radius: 1.5rem; background: var(--card-bg); padding: 5rem 1.5rem; text-align: center; border: 1px solid var(--border-color); overflow: hidden;">
            <div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, transparent 60%); z-index: 1; pointer-events: none;"></div>
            <div class="relative max-w-3xl mx-auto space-y-6" style="position: relative; z-index: 2; max-width: 48rem; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem;">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-indigo-500/10 text-indigo-400 border border-indigo-500/20" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: rgba(99, 102, 241, 0.1); color: var(--primary-color); border: 1px solid rgba(99, 102, 241, 0.2);">
                        🚀 {{ __('ui.welcome.next_gen') }}
                    </span>
                </div>
                <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight text-white font-outfit" style="font-family: 'Outfit', sans-serif; font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 800; color: var(--text-color); margin: 0; line-height: 1.1;">
                    DevSense<span style="color: var(--primary-color);">.</span>
                </h1>
                <p class="text-lg md:text-xl text-slate-300 max-w-2xl mx-auto leading-relaxed" style="font-size: 1.125rem; color: var(--text-muted); line-height: 1.6; max-width: 42rem; margin: 0 auto;">
                    {{ __('ui.welcome.ecosystem_lead') }}
                </p>
                <div class="flex flex-wrap justify-center gap-4 pt-4" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem; padding-top: 1rem;">
                    <a href="{{ route('search') }}" class="btn-primary glow-button" style="padding: 0.75rem 1.5rem; border-radius: 0.75rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center;">
                        {{ __('ui.welcome.browse_catalog') }}
                    </a>
                    <a href="{{ route('quizzes.index') }}" class="btn-secondary" style="padding: 0.75rem 1.5rem; border-radius: 0.75rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center;">
                        {{ __('ui.welcome.try_quizzes') }}
                    </a>
                </div>
            </div>
        </section>

        <!-- Three main sections: Articles, Quizzes, Suggestions -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <!-- Articles Block -->
            <section class="glass-card" style="display: flex; flex-direction: column; justify-content: space-between; padding: 2rem; border-radius: 1.5rem; background: var(--card-bg); border: 1px solid var(--border-color); transition: all 0.3s ease;">
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                        <div style="width: 3rem; height: 3rem; border-radius: 1rem; background: rgba(99, 102, 241, 0.1); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            📚
                        </div>
                        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
                            {{ __('ui.welcome.guides') }}
                        </span>
                    </div>
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--text-color); margin: 0 0 1rem 0;">
                        {{ __('ui.welcome.technical_articles') }}
                    </h2>
                    <p style="font-size: 0.875rem; color: var(--text-muted); line-height: 1.5; margin: 0 0 1.5rem 0;">
                        {{ __('ui.welcome.fresh_guides') }}
                    </p>

                    <!-- Latest Articles List -->
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        @foreach($latestArticles as $article)
                            @php $trans = $article->translate(); @endphp
                            <a href="{{ $article->url() }}" class="article-item-link" style="display: block; padding: 1rem; border-radius: 1rem; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); text-decoration: none; transition: all 0.2s ease;">
                                <span style="font-size: 0.65rem; color: var(--primary-color); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem;">
                                    {{ $article->category?->translate()?->name ?? $article->category?->slug }}
                                </span>
                                <h3 style="font-size: 0.9rem; font-weight: 600; color: var(--text-color); margin: 0; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ $trans?->title ?? $article->slug }}
                                </h3>
                                <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0.25rem 0 0 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4;">
                                    {{ $trans?->description }}
                                </p>
                            </a>
                        @endforeach
                    </div>
                </div>

                <a href="{{ route('search') }}" class="btn-secondary" style="display: block; text-align: center; padding: 0.75rem; border-radius: 0.75rem; font-size: 0.875rem; font-weight: 600; text-decoration: none;">
                    {{ __('ui.welcome.all_articles') }} &rarr;
                </a>
            </section>

            <!-- Quizzes Block -->
            <section class="glass-card" style="display: flex; flex-direction: column; justify-content: space-between; padding: 2rem; border-radius: 1.5rem; background: var(--card-bg); border: 1px solid var(--border-color); transition: all 0.3s ease;">
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                        <div style="width: 3rem; height: 3rem; border-radius: 1rem; background: rgba(168, 85, 247, 0.1); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            🎮
                        </div>
                        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
                            {{ __('ui.welcome.try_quizzes') }}
                        </span>
                    </div>
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--text-color); margin: 0 0 1rem 0;">
                        {{ __('ui.welcome.interview_quizzes') }}
                    </h2>
                    <p style="font-size: 0.875rem; color: var(--text-muted); line-height: 1.5; margin: 0 0 1.5rem 0;">
                        {{ __('ui.welcome.quizzes_lead') }}
                    </p>

                    <!-- Latest Quizzes List -->
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        @foreach($latestQuizzes as $quiz)
                            @php $qTrans = $quiz->translate(); @endphp
                            <a href="{{ route('quizzes.show', ['slug' => $quiz->slug]) }}" class="article-item-link" style="display: block; padding: 1rem; border-radius: 1rem; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); text-decoration: none; transition: all 0.2s ease;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.25rem;">
                                    <span style="font-size: 0.65rem; color: #a855f7; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                                        +{{ $quiz->points }} XP
                                    </span>
                                </div>
                                <h3 style="font-size: 0.9rem; font-weight: 600; color: var(--text-color); margin: 0; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ $qTrans?->title ?? 'Technical Quiz' }}
                                </h3>
                                <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0.25rem 0 0 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4;">
                                    {{ $qTrans?->description }}
                                </p>
                            </a>
                        @endforeach
                    </div>
                </div>

                <a href="{{ route('quizzes.index') }}" class="btn-secondary" style="display: block; text-align: center; padding: 0.75rem; border-radius: 0.75rem; font-size: 0.875rem; font-weight: 600; text-decoration: none;">
                    {{ __('ui.welcome.all_quizzes') }} &rarr;
                </a>
            </section>

            <!-- Suggestions Block -->
            <section class="glass-card" style="display: flex; flex-direction: column; justify-content: space-between; padding: 2rem; border-radius: 1.5rem; background: var(--card-bg); border: 1px solid var(--border-color); transition: all 0.3s ease;">
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                        <div style="width: 3rem; height: 3rem; border-radius: 1rem; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            💡
                        </div>
                        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
                            {{ __('ui.welcome.ideas') }}
                        </span>
                    </div>
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--text-color); margin: 0 0 1rem 0;">
                        {{ __('ui.welcome.community_board') }}
                    </h2>
                    <p style="font-size: 0.875rem; color: var(--text-muted); line-height: 1.5; margin: 0 0 1.5rem 0;">
                        {{ __('ui.welcome.suggestions_lead') }}
                    </p>

                    <!-- Latest Suggestions List -->
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        @foreach($latestSuggestions as $suggestion)
                            <div style="padding: 1rem; border-radius: 1rem; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 0.5rem;">
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem;">
                                    <span style="font-weight: 600; color: var(--text-color);">{{ $suggestion->user?->name ?? 'Guest' }}</span>
                                    <span style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 0.15rem 0.5rem; border-radius: 9999px; font-weight: 700; font-size: 0.65rem;">
                                        {{ $suggestion->votes()->count() }} {{ __('ui.welcome.votes') }}
                                    </span>
                                </div>
                                <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4;">
                                    {{ $suggestion->content }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <a href="{{ route('suggestions.index') }}" class="btn-secondary" style="display: block; text-align: center; padding: 0.75rem; border-radius: 0.75rem; font-size: 0.875rem; font-weight: 600; text-decoration: none;">
                    {{ __('ui.welcome.view_suggestions') }} &rarr;
                </a>
            </section>
        </div>
    </div>

    <style>
        .article-item-link:hover {
            border-color: var(--primary-color) !important;
            background: rgba(99, 102, 241, 0.04) !important;
        }
    </style>
</x-layout>
