<x-layout 
    :title="$course->translate()?->title . ' | DevSense'" 
    :description="$course->translate()?->description"
    :breadcrumb-current="$course->translate()?->title"
>
    <div class="quizzes-container">
        <div style="margin-bottom: 1.5rem;">
            <a href="{{ route('courses.index', ['locale' => app()->getLocale()]) }}" style="color: var(--primary-color); text-decoration: none; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
                &larr; {{ __('ui.courses.back_to_courses') }}
            </a>
        </div>

        <!-- Course Header Card -->
        <section class="progress-section" style="margin-bottom: 2.5rem;">
            <div class="progress-card glass-card" style="padding: 2rem;">
                <h1 style="font-family: 'Outfit', sans-serif; font-size: 2rem; margin: 0 0 1rem 0; color: var(--text-color);">
                    {{ $course->translate()?->title }}
                </h1>
                <p style="color: rgba(255,255,255,0.7); line-height: 1.6; font-size: 1rem; margin: 0 0 1.5rem 0; max-width: 800px;">
                    {{ $course->translate()?->description }}
                </p>

                <!-- Course Stats Badge Row -->
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.9rem; color: rgba(255,255,255,0.8);">
                        <strong>{{ __('ui.courses.total_chapters') }}</strong> {{ $course->chapters->count() }}
                    </div>
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.9rem; color: rgba(255,255,255,0.8);">
                        <strong>{{ __('ui.courses.total_reward') }}</strong> {{ $course->points }} XP
                    </div>
                </div>
            </div>
        </section>

        <!-- Chapters List / Syllabus -->
        <section class="quizzes-grid-section">
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; margin: 0 0 1.5rem 0; color: var(--text-color);">
                {{ __('ui.courses.course_syllabus') }}
            </h2>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach($course->chapters as $index => $chapter)
                    @php
                        $chapterTrans = $chapter->translate();
                        $score = $completedChapters[$chapter->id] ?? null;
                        $isCompleted = !is_null($score);
                    @endphp

                    <div class="glass-card" style="padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; border-left: 4px solid {{ $isCompleted ? 'var(--primary-color)' : 'rgba(255,255,255,0.1)' }}; transition: all 0.2s ease;">
                        <div style="display: flex; align-items: center; gap: 1.5rem; flex-grow: 1;">
                            <!-- Chapter Number -->
                            <div style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; font-weight: bold; color: {{ $isCompleted ? 'var(--primary-color)' : 'rgba(255,255,255,0.2)' }}; width: 30px; text-align: center;">
                                {{ sprintf("%02d", $index + 1) }}
                            </div>

                            <!-- Chapter Info -->
                            <div>
                                <h3 style="margin: 0 0 0.25rem 0; font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 600; color: var(--text-color);">
                                    {{ $chapterTrans?->title }}
                                </h3>
                                <div style="display: flex; align-items: center; gap: 1rem; font-size: 0.8rem; color: rgba(255,255,255,0.4);">
                                    <span>Slug: <code>{{ $chapter->slug }}</code></span>
                                    @if($isCompleted)
                                        <span style="color: #10B981; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <svg xmlns="http://www.w3.org/2000/svg" style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                            {{ __('ui.courses.completed') }} ({{ $score }} XP)
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div>
                            <a href="{{ route('courses.chapter', ['locale' => app()->getLocale(), 'course_slug' => $course->slug, 'chapter_slug' => $chapter->slug]) }}" class="{{ $isCompleted ? 'btn-secondary' : 'btn-primary' }}" style="white-space: nowrap; text-decoration: none; padding: 0.5rem 1.25rem; font-size: 0.9rem;">
                                @if($isCompleted)
                                    {{ __('ui.courses.review') }}
                                @else
                                    {{ __('ui.courses.start_lesson') }} &rarr;
                                @endif
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-layout>
