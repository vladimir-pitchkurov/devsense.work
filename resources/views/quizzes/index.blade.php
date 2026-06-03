<x-layout 
    :title="app()->getLocale() === 'ru' ? 'Квизы и Геймификация | DevSense' : 'Interview Quizzes & Gamification | DevSense'" 
    :description="app()->getLocale() === 'ru' ? 'Проверьте свои технические знания по PHP, Laravel и архитектуре. Набирайте очки и открывайте достижения!' : 'Test your technical knowledge of PHP, Laravel, and software architecture. Score points and unlock badges!'"
>
    <div class="quizzes-container">
        <!-- Hero Header -->
        <section class="quizzes-hero">
            <div class="hero-glow"></div>
            <h1 class="hero-title" style="font-family: 'Outfit', sans-serif;">
                {{ app()->getLocale() === 'ru' ? 'Квизы и Геймификация' : 'Interview Quizzes & Gamification' }}
            </h1>
            <p class="hero-lead">
                {{ app()->getLocale() === 'ru' ? 'Интерактивные тесты для подготовки к техническим собеседованиям. Прокачайте навыки, зарабатывайте баллы и открывайте бейджи.' : 'Interactive challenges to prepare for technical interviews. Level up your skills, score points, and unlock achievements.' }}
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
                            <span class="points-lbl">{{ app()->getLocale() === 'ru' ? 'Баллов' : 'Points' }}</span>
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
                                <span>{{ app()->getLocale() === 'ru' ? 'Прогресс достижений' : 'Achievement Progress' }}</span>
                                @if($nextBadge)
                                    <span>{{ $user->points }} / {{ $targetPoints }} {{ app()->getLocale() === 'ru' ? 'баллов' : 'points' }}</span>
                                @else
                                    <span>{{ app()->getLocale() === 'ru' ? 'Максимальный ранг!' : 'Max Rank Unlocked!' }}</span>
                                @endif
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width: {{ $percent }}%"></div>
                            </div>
                            @if($nextBadge)
                                <p class="next-badge-info">
                                    {{ app()->getLocale() === 'ru' ? 'Следующий бейдж:' : 'Next badge:' }} 
                                    <strong>{{ $nextBadge->translate()?->title }}</strong> 
                                    ({{ app()->getLocale() === 'ru' ? 'нужно' : 'requires' }} {{ $nextBadge->points_required }})
                                </p>
                            @endif
                        </div>

                        <!-- Unlocked Badges Row -->
                        <div class="badges-row-section">
                            <h3 class="section-subtitle" style="font-family: 'Outfit', sans-serif;">
                                {{ app()->getLocale() === 'ru' ? 'Ваши бейджи' : 'Your Badges' }}
                            </h3>
                            @if($unlockedBadges->isEmpty())
                                <p class="empty-badges-msg">
                                    {{ app()->getLocale() === 'ru' ? 'Пройдите первый квиз, чтобы разблокировать награду!' : 'Complete your first quiz to unlock a badge!' }}
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
                                {{ app()->getLocale() === 'ru' 
                                    ? 'Пожалуйста, подтвердите ваш имейл, чтобы разблокировать бейджи и прогресс достижений.' 
                                    : 'Please verify your email address to unlock badges and achievement progress.' }}
                            </p>
                            <a href="{{ route('verification.notice') }}" style="display: inline-block; margin-top: 0.5rem; font-size: 0.85rem; color: var(--primary-color); font-weight: 600; text-decoration: none;">
                                {{ app()->getLocale() === 'ru' ? 'Подтвердить имейл' : 'Verify Email' }} &rarr;
                            </a>
                        </div>
                    @endif
                </div>
            @else
                <!-- Guest CTA -->
                <div class="guest-cta-card glass-card">
                    <div class="cta-content">
                        <h2 class="cta-title" style="font-family: 'Outfit', sans-serif;">
                            {{ app()->getLocale() === 'ru' ? 'Хотите отслеживать свои результаты?' : 'Want to track your progress?' }}
                        </h2>
                        <p class="cta-lead">
                            {{ app()->getLocale() === 'ru' ? 'Авторизуйтесь, чтобы копить баллы за верные ответы, разблокировать престижные бейджи и войти в глобальный рейтинг разработчиков!' : 'Sign in to save your quiz answers, accumulate XP points, unlock rare achievement badges, and show off your technical expertise!' }}
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
                {{ app()->getLocale() === 'ru' ? 'Доступные испытания' : 'Available Challenges' }}
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
                                    <span class="check-mark">✓</span> {{ app()->getLocale() === 'ru' ? 'Пройден' : 'Completed' }}
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
                                    {{ app()->getLocale() === 'ru' ? 'Счёт:' : 'Score:' }} <strong>{{ $userScore }}/{{ $quiz->points }}</strong>
                                </div>
                            @endif
                            <a href="{{ route('quizzes.show', ['slug' => $quiz->slug]) }}" class="quiz-action-btn {{ $isCompleted ? 'btn-outline' : 'btn-glow' }}">
                                @if($isCompleted)
                                    {{ app()->getLocale() === 'ru' ? 'Пройти снова' : 'Retake Quiz' }}
                                @else
                                    {{ app()->getLocale() === 'ru' ? 'Начать тест' : 'Start Quiz' }}
                                @endif
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div></x-layout>
