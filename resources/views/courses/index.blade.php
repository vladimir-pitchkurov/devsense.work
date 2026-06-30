<x-layout 
    :title="__('ui.courses.title')" 
    :description="__('ui.courses.description')"
>
    <div class="quizzes-container">
        <!-- Hero Header -->
        <section class="quizzes-hero" style="margin-bottom: 2rem;">
            <div class="hero-glow"></div>
            <h1 class="hero-title" style="font-family: 'Outfit', sans-serif;">
                {{ __('ui.courses.hero_title') }}
            </h1>
            <p class="hero-lead">
                {{ __('ui.courses.hero_lead') }}
            </p>
        </section>

        <!-- Courses List -->
        <section class="quizzes-grid-section">
            <div class="quizzes-grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
                @foreach($courses as $course)
                    @php
                        $trans = $course->translate();
                        $courseProgress = $progress[$course->id] ?? null;
                        
                        // Set specific styling/icons for each course
                        $badgeEmoji = '🎓';
                        $difficultyColor = '#10B981'; // Green
                        $difficultyName = __('ui.courses.level_beginner');
                        
                        if ($course->slug === 'sql-advanced') {
                            $badgeEmoji = '🚀';
                            $difficultyColor = '#F59E0B'; // Amber
                            $difficultyName = __('ui.courses.level_advanced');
                        } elseif ($course->slug === 'sql-expert') {
                            $badgeEmoji = '⚡';
                            $difficultyColor = '#EF4444'; // Red
                            $difficultyName = __('ui.courses.level_expert');
                        }
                    @endphp

                    <div class="quiz-card glass-card" style="display: flex; flex-direction: column; justify-content: space-between; height: 100%;">
                        <div>
                            <div class="quiz-card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                                <div class="points-badge" style="background: rgba(139, 92, 246, 0.1); color: #a78bfa; padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: bold; font-size: 0.85rem;">
                                    +{{ $course->points }} XP
                                </div>
                                <div style="font-size: 0.8rem; background: {{ $difficultyColor }}1a; color: {{ $difficultyColor }}; padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 600;">
                                    {{ $difficultyName }}
                                </div>
                            </div>

                            <div style="display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1rem;">
                                <div style="font-size: 2.5rem; line-height: 1; padding: 0.5rem; background: rgba(255,255,255,0.03); border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                                    {{ $badgeEmoji }}
                                </div>
                                <div>
                                    <h3 style="margin: 0 0 0.5rem 0; font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: bold; color: var(--text-color);">
                                        {{ $trans?->title }}
                                    </h3>
                                    <p style="margin: 0; font-size: 0.9rem; color: rgba(255,255,255,0.6); line-height: 1.5;">
                                        {{ $trans?->description }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem;">
                            @if($user)
                                @if($courseProgress)
                                    <!-- Progress Tracker -->
                                    <div style="margin-bottom: 1.25rem;">
                                        <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: rgba(255,255,255,0.5); margin-bottom: 0.5rem;">
                                            <span>{{ __('ui.courses.course_progress') }}</span>
                                            <span>{{ $courseProgress['completed'] }} / {{ $courseProgress['total'] }} {{ __('ui.courses.chapters') }}</span>
                                        </div>
                                        <div style="height: 6px; background: rgba(255,255,255,0.08); border-radius: 9999px; overflow: hidden;">
                                            <div style="height: 100%; background: var(--primary-color); width: {{ $courseProgress['percentage'] }}%; border-radius: 9999px; transition: width 0.3s ease;"></div>
                                        </div>
                                    </div>
                                @endif
                                <a href="{{ route('courses.show', ['locale' => app()->getLocale(), 'course_slug' => $course->slug]) }}" class="btn-primary" style="display: block; text-align: center; text-decoration: none;">
                                    {{ __('ui.courses.open_syllabus') }} &rarr;
                                </a>
                            @else
                                <div style="background: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255,255,255,0.1); border-radius: 8px; padding: 0.75rem; text-align: center; margin-bottom: 1rem;">
                                    <p style="margin: 0; font-size: 0.8rem; color: rgba(255,255,255,0.5);">
                                        {{ __('ui.courses.login_required') }}
                                    </p>
                                </div>
                                <a href="{{ route('login.locale', ['locale' => app()->getLocale()]) }}" class="btn-secondary" style="display: block; text-align: center; text-decoration: none;">
                                    {{ __('ui.courses.login_and_start') }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-layout>
