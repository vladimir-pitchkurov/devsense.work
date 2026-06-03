<x-layout title="Cabinet - My Profile | DevSense" description="Personal Developer Dashboard & Achievements">
<div class="admin-container" style="max-width: 1200px; margin: 0 auto; padding: 1.5rem;">
    <!-- Profile Header Section -->
    <div class="admin-header" style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 1.25rem;">
            <div style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; border: 3px solid var(--primary-color); flex-shrink: 0;">
                <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div>
                <h1 class="admin-title" style="margin: 0; font-size: 1.75rem; font-family: 'Outfit', sans-serif;">{{ $user->name }}</h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.95rem;">
                    {{ $user->job_title ?? (app()->getLocale() === 'ru' ? 'Разработчик' : 'Developer') }}
                    &bull; <span class="admin-badge admin-badge--category">{{ strtoupper($user->role) }}</span>
                </p>
                @if($user->bio)
                    <p style="margin: 0.5rem 0 0; font-size: 0.85rem; color: var(--text-color); max-width: 600px; line-height: 1.4;">{{ Str::limit($user->bio, 180) }}</p>
                @endif
            </div>
        </div>
        <div class="header-actions" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('admin.profile.edit', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                {{ app()->getLocale() === 'ru' ? 'Редактировать профиль' : 'Edit Profile' }}
            </a>
            <a href="{{ route('admin.articles.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                {{ app()->getLocale() === 'ru' ? 'Мои статьи' : 'My Articles' }}
            </a>
            <form action="{{ route('logout') }}" method="POST" style="display: inline-block; margin: 0;">
                @csrf
                <button type="submit" class="admin-btn admin-btn--danger" style="display: inline-flex; align-items: center; gap: 0.5rem; border: none; cursor: pointer;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    {{ app()->getLocale() === 'ru' ? 'Выйти' : 'Logout' }}
                </button>
            </form>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <!-- Stat Card 1: Experience -->
        <div class="stat-card" style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem;">
            <div class="stat-card__icon-wrapper stat-card__icon-wrapper--blue" style="width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
            </div>
            <div>
                <div class="stat-card__label" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">{{ app()->getLocale() === 'ru' ? 'Очки Опыта (XP)' : 'Experience Points (XP)' }}</div>
                <div class="stat-card__value" style="font-size: 1.75rem; font-weight: 700; color: var(--text-color); margin-top: 0.25rem;">{{ number_format($user->points) }}</div>
            </div>
        </div>

        <!-- Stat Card 2: Articles -->
        <div class="stat-card" style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem;">
            <div class="stat-card__icon-wrapper stat-card__icon-wrapper--green" style="width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                    <path d="M12 20h9"></path>
                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                </svg>
            </div>
            <div>
                <div class="stat-card__label" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">{{ app()->getLocale() === 'ru' ? 'Публикации' : 'Articles Published' }}</div>
                <div class="stat-card__value" style="font-size: 1.75rem; font-weight: 700; color: var(--text-color); margin-top: 0.25rem;">{{ $publishedArticlesCount }}</div>
            </div>
        </div>

        <!-- Stat Card 3: Badges -->
        <div class="stat-card" style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem;">
            <div class="stat-card__icon-wrapper stat-card__icon-wrapper--indigo" style="width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                    <circle cx="12" cy="8" r="7"></circle>
                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                </svg>
            </div>
            <div>
                <div class="stat-card__label" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">{{ app()->getLocale() === 'ru' ? 'Бейджи и Звания' : 'Badges Unlocked' }}</div>
                <div class="stat-card__value" style="font-size: 1.75rem; font-weight: 700; color: var(--text-color); margin-top: 0.25rem;">{{ $user->badges->count() }}</div>
            </div>
        </div>

        <!-- Stat Card 4: Quizzes -->
        <div class="stat-card" style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem;">
            <div class="stat-card__icon-wrapper stat-card__icon-wrapper--purple" style="width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                    <path d="M18.065 9.586a10.001 10.001 0 1 1-12.13 0"></path>
                    <path d="M12 6V12h6"></path>
                </svg>
            </div>
            <div>
                <div class="stat-card__label" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">{{ app()->getLocale() === 'ru' ? 'Квизы' : 'Quizzes Taken' }}</div>
                <div class="stat-card__value" style="font-size: 1.75rem; font-weight: 700; color: var(--text-color); margin-top: 0.25rem;">{{ $user->quizzes->count() }}</div>
            </div>
        </div>
    </div>

    <!-- Main Grid Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
        <!-- Left Column: Achievements -->
        <div class="admin-card" style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; min-height: 350px;">
            <h2 class="card-title" style="margin-top: 0; font-family: 'Outfit', sans-serif; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 1.4rem;">🏆</span>
                {{ app()->getLocale() === 'ru' ? 'Мои достижения' : 'My Achievements' }}
            </h2>
            @if($user->badges->isEmpty())
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; flex-grow: 1; text-align: center; color: var(--text-muted); padding: 2rem 1rem;">
                    <p style="margin: 0; font-size: 0.95rem;">
                        {{ app()->getLocale() === 'ru' ? 'Вы еще не разблокировали ни одного бейджа.' : 'You have not unlocked any badges yet.' }}
                    </p>
                    <p style="margin: 0.5rem 0 0; font-size: 0.85rem; color: var(--text-muted);">
                        {{ app()->getLocale() === 'ru' ? 'Проходите квизы или публикуйте статьи, чтобы заработать бейджи!' : 'Complete quizzes or publish articles to earn your badges!' }}
                    </p>
                </div>
            @else
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    @foreach($user->badges as $badge)
                        @php
                            $trans = $badge->translate();
                        @endphp
                        <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); border-radius: 10px; padding: 1rem 0.5rem; text-align: center; transition: transform 0.2s, box-shadow 0.2s; position: relative;" 
                             onmouseenter="this.style.transform='translateY(-2px)';" 
                             onmouseleave="this.style.transform='none';">
                            <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), #818cf8); display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.25);">
                                <span style="font-size: 1.5rem; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));">🏅</span>
                            </div>
                            <h3 style="margin: 0; font-size: 0.85rem; font-weight: 600; color: var(--text-color); line-height: 1.2;">{{ $trans?->title ?? $badge->slug }}</h3>
                            <p style="margin: 0.25rem 0 0; font-size: 0.75rem; color: var(--text-muted); line-height: 1.3; padding: 0 0.25rem;">{{ $trans?->description }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Right Column: Challenges -->
        <div class="admin-card" style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; min-height: 350px;">
            <h2 class="card-title" style="margin-top: 0; font-family: 'Outfit', sans-serif; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 1.4rem;">⚡</span>
                {{ app()->getLocale() === 'ru' ? 'Незавершенные задания' : 'Active Challenges' }}
            </h2>

            <!-- Challenge: Write First Article (Reader only) -->
            @if($user->role === \App\Models\User::ROLE_READER)
                <div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.05)); border: 1px dashed var(--primary-color); border-radius: 10px; padding: 1.25rem; margin-top: 1rem; display: flex; gap: 0.75rem; align-items: flex-start;">
                    <div style="font-size: 1.75rem; line-height: 1; flex-shrink: 0; filter: grayscale(0.2);">📝</div>
                    <div>
                        <h3 style="margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-color);">
                            {{ app()->getLocale() === 'ru' ? 'Станьте Автором!' : 'Become an Author!' }}
                        </h3>
                        <p style="margin: 0.35rem 0 0.75rem; font-size: 0.8rem; color: var(--text-muted); line-height: 1.4;">
                            {{ app()->getLocale() === 'ru' ? 'Напишите свою первую статью и отправьте на модерацию. После утверждения вы получите роль Автора, 100 XP и бейдж!' : 'Write and submit your first article. Once approved and published, you will unlock the Author role, get +100 XP, and earn a badge!' }}
                        </p>
                        <a href="{{ route('admin.articles.create', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--primary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem;">
                            {{ app()->getLocale() === 'ru' ? 'Написать статью' : 'Write Article' }}
                        </a>
                    </div>
                </div>
            @endif

            <!-- Incomplete Quizzes List -->
            <div style="margin-top: 1.25rem; flex-grow: 1;">
                <h3 style="margin: 0 0 0.75rem; font-size: 0.95rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                    {{ app()->getLocale() === 'ru' ? 'Доступные квизы' : 'Available Quizzes' }}
                </h3>
                @if($incompleteQuizzes->isEmpty())
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: var(--text-muted); padding: 2rem 0;">
                        <p style="margin: 0; font-size: 0.85rem;">🎉 {{ app()->getLocale() === 'ru' ? 'Вы прошли все доступные квизы!' : 'You have completed all available quizzes!' }}</p>
                    </div>
                @else
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        @foreach($incompleteQuizzes as $quiz)
                            @php
                                $trans = $quiz->translate();
                            @endphp
                            <div style="background: rgba(255, 255, 255, 0.01); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                                <div>
                                    <h4 style="margin: 0; font-size: 0.85rem; font-weight: 600; color: var(--text-color);">{{ $trans?->title ?? $quiz->slug }}</h4>
                                    <p style="margin: 0.2rem 0 0; font-size: 0.75rem; color: var(--text-muted);">{{ app()->getLocale() === 'ru' ? 'Награда:' : 'Award:' }} {{ $quiz->points }} XP</p>
                                </div>
                                <a href="{{ route('quizzes.show', ['slug' => $quiz->slug, 'locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 600;">
                                    {{ app()->getLocale() === 'ru' ? 'Пройти квиз' : 'Start Quiz' }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
</x-layout>
