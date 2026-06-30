<x-layout 
    :title="__('ui.quizzes.index_title')" 
    :description="__('ui.quizzes.index_desc')"
>
    <div class="quizzes-container">
        <!-- Hero Header -->
        <section class="quizzes-hero">
            <div class="hero-glow"></div>
            <h1 class="hero-title" style="font-family: 'Outfit', sans-serif;">
                {{ __('ui.quizzes.hero_title') }}
            </h1>
            <p class="hero-lead">
                {{ __('ui.quizzes.hero_lead') }}
            </p>
        </section>

        <!-- Progress and Stats Section -->
        <section class="progress-section">
            @if($user)
                <div class="progress-card glass-card">
                    <div class="user-info">
                        <div class="user-avatar-wrapper">
                            <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="user-avatar">
                        </div>
                        <div class="user-meta">
                            <h2 class="user-name" style="font-family: 'Outfit', sans-serif;">{{ $user->name }}</h2>
                            <p class="user-role">{{ strtoupper($user->role) }}</p>
                        </div>
                        <div class="user-points-badge">
                            <span class="points-val">{{ $user->points }}</span>
                            <span class="points-lbl">{{ __('ui.quizzes.points') }}</span>
                        </div>
                    </div>

                    @if($user->hasVerifiedEmail())
                        <!-- Progress Bar for Badges -->
                        @php
                            $nextBadge = $allBadges->where('points_required', '>', $user->points)->sortBy('points_required')->first();
                            $prevBadgeThreshold = 0;
                            if ($nextBadge) {
                                $prevBadge = $allBadges->where('points_required', '<=', $user->points)->sortByDesc('points_required')->first();
                                $prevBadgeThreshold = $prevBadge ? $prevBadge->points_required : 0;
                                $targetPoints = $nextBadge->points_required;
                                $range = $targetPoints - $prevBadgeThreshold;
                                $currentOffset = $user->points - $prevBadgeThreshold;
                                $percent = $range > 0 ? min(100, max(0, ($currentOffset / $range) * 100)) : 100;
                            } else {
                                $percent = 100;
                                $targetPoints = $user->points;
                            }
                        @endphp

                        <div class="progress-bar-container">
                            <div class="progress-bar-labels">
                                <span>{{ __('ui.quizzes.achievement_progress') }}</span>
                                @if($nextBadge)
                                    <span>{{ $user->points }} / {{ $targetPoints }} {{ __('ui.quizzes.points_short') }}</span>
                                @else
                                    <span>{{ __('ui.quizzes.max_rank') }}</span>
                                @endif
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width: {{ $percent }}%"></div>
                            </div>
                            @if($nextBadge)
                                <p class="next-badge-info">
                                    {{ __('ui.quizzes.next_badge') }} 
                                    <strong>{{ $nextBadge->translate()?->title }}</strong> 
                                    ({{ __('ui.quizzes.requires') }} {{ $nextBadge->points_required }})
                                </p>
                            @endif
                        </div>

                        <!-- Unlocked Badges Row -->
                        <div class="badges-row-section">
                            <h3 class="section-subtitle" style="font-family: 'Outfit', sans-serif;">
                                {{ __('ui.quizzes.your_badges') }}
                            </h3>
                            @if($unlockedBadges->isEmpty())
                                <p class="empty-badges-msg">
                                    {{ __('ui.quizzes.first_quiz_hint') }}
                                </p>
                            @else
                                <div class="unlocked-badges-grid">
                                    @foreach($unlockedBadges as $badge)
                                        <div class="badge-item tooltipped" data-tooltip="{{ $badge->translate()?->description }}">
                                            <div class="badge-icon-wrapper active-badge">
                                                <!-- Glowing Circle -->
                                                <div class="badge-glow"></div>
                                                <span class="badge-emoji">🏆</span>
                                            </div>
                                            <span class="badge-title">{{ $badge->translate()?->title }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="unverified-reminder-box" style="margin-top: 1.5rem; padding: 1rem; background: rgba(239, 68, 68, 0.05); border: 1px dashed rgba(239, 68, 68, 0.2); border-radius: 8px; text-align: center;">
                            <p style="margin: 0; font-size: 0.9rem; color: var(--text-color); line-height: 1.5;">
                                {{ __('ui.quizzes.verify_unverified_hint') }}
                            </p>
                            <a href="{{ route('verification.notice') }}" style="display: inline-block; margin-top: 0.5rem; font-size: 0.85rem; color: var(--primary-color); font-weight: 600; text-decoration: none;">
                                {{ __('ui.quizzes.verify_email_btn') }} &rarr;
                            </a>
                        </div>
                    @endif
                </div>
            @else
                <!-- Guest CTA -->
                <div class="guest-cta-card glass-card">
                    <div class="cta-content">
                        <h2 class="cta-title" style="font-family: 'Outfit', sans-serif;">
                            {{ __('ui.quizzes.track_progress_title') }}
                        </h2>
                        <p class="cta-lead">
                            {{ __('ui.quizzes.track_progress_lead') }}
                        </p>
                        <div class="cta-actions">
                            <a href="{{ route('login.locale') }}" class="btn-primary glow-button">
                                {{ __('ui.nav.login') }}
                            </a>
                            <a href="{{ route('register.locale') }}" class="btn-secondary">
                                {{ __('ui.nav.register') }}
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        <!-- Quizzes Grid -->
        <section class="quizzes-grid-section">
            <h2 class="section-title" style="font-family: 'Outfit', sans-serif;">
                {{ __('ui.quizzes.available_challenges') }}
            </h2>
            
            <div class="quizzes-grid">
                @foreach($quizzes as $quiz)
                    @php
                        $quizTrans = $quiz->translate();
                        $isCompleted = $completedQuizzes->has($quiz->id);
                        $userScore = $isCompleted ? $completedQuizzes->get($quiz->id) : null;
                    @endphp
                    <div class="quiz-card glass-card {{ $isCompleted ? 'quiz-completed' : '' }}">
                        <div class="quiz-card-header">
                            <div class="points-badge">
                                +{{ $quiz->points }} XP
                            </div>
                            @if($isCompleted)
                                <span class="status-indicator completed">
                                    <span class="check-mark">✓</span> {{ __('ui.quizzes.completed_badge') }}
                                </span>
                            @endif
                        </div>
                        <div class="quiz-card-body">
                            <h3 class="quiz-card-title" style="font-family: 'Outfit', sans-serif;">
                                {{ $quizTrans?->title ?? 'Technical Quiz' }}
                            </h3>
                            <p class="quiz-card-desc">
                                {{ $quizTrans?->description ?? 'Test your knowledge on this subject.' }}
                            </p>
                        </div>
                        <div class="quiz-card-footer">
                            @if($isCompleted)
                                <div class="score-display">
                                    {{ __('ui.quizzes.score') }} <strong>{{ $userScore }}/{{ $quiz->points }}</strong>
                                </div>
                            @endif
                            <a href="{{ route('quizzes.show', ['slug' => $quiz->slug]) }}" class="quiz-action-btn {{ $isCompleted ? 'btn-outline' : 'btn-glow' }}">
                                @if($isCompleted)
                                    {{ __('ui.quizzes.retake_quiz_btn') }}
                                @else
                                    {{ __('ui.quizzes.start_quiz') }}
                                @endif
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div></x-layout>
