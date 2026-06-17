<x-layout 
    :title="$chapter->translate()?->title . ' | ' . $course->translate()?->title" 
    :description="strip_tags(Str::limit($htmlContent, 150))"
    :breadcrumb-current="$chapter->translate()?->title"
>
    <div class="quizzes-container">
        <!-- Navigation Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <a href="{{ route('courses.show', ['locale' => app()->getLocale(), 'course_slug' => $course->slug]) }}" style="color: var(--primary-color); text-decoration: none; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
                &larr; {{ app()->getLocale() === 'ru' ? 'К силлабусу курса' : 'Back to Syllabus' }}
            </a>
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                {{ $course->translate()?->title }}
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr; gap: 2.5rem; align-items: start;">
            @if(app()->getLocale() === 'ru')
                <!-- Title & Header -->
                <div style="margin-bottom: 1rem;">
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.25rem; margin: 0 0 0.5rem 0; color: var(--text-color);">
                        {{ $chapter->translate()?->title }}
                    </h1>
                </div>
            @else
                <div style="margin-bottom: 1rem;">
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.25rem; margin: 0 0 0.5rem 0; color: var(--text-color);">
                        {{ $chapter->translate()?->title }}
                    </h1>
                </div>
            @endif

            <!-- Left/Main Column: Theory -->
            <div class="glass-card" style="padding: 2rem; overflow-x: auto; line-height: 1.7; color: var(--text-color); font-size: 1.05rem;">
                <div class="markdown-body">
                    {!! $htmlContent !!}
                </div>
            </div>

            <!-- Right/Bottom Column: Chapter Quiz -->
            @if($chapter->quiz)
                <div class="quiz-box glass-card" id="quiz-panel" style="padding: 2rem; border-top: 4px solid var(--primary-color);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 1rem;">
                        <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; margin: 0; color: var(--text-color);">
                            {{ app()->getLocale() === 'ru' ? 'Проверка знаний' : 'Knowledge Check' }}
                        </h2>
                        <div style="font-size: 0.85rem; background: rgba(139, 92, 246, 0.1); color: #a78bfa; padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: bold;">
                            +{{ $chapter->quiz->points }} XP
                        </div>
                    </div>

                    <form id="chapter-quiz-form" class="{{ $progress ? 'submitted' : '' }}" onsubmit="submitQuiz(event)">
                        @csrf
                        <div style="display: flex; flex-direction: column; gap: 2rem;">
                            @foreach($chapter->quiz->questions as $qIndex => $question)
                                @php
                                    $qTrans = $question->translate();
                                    $savedAnswer = $progress ? ($progress->answers[$question->id] ?? null) : null;
                                @endphp

                                <div class="question-item" data-question-id="{{ $question->id }}" style="background: var(--page-bg); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: 12px;">
                                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; margin: 0 0 1rem 0; line-height: 1.5; color: var(--text-color);">
                                        <span style="color: var(--primary-color); font-weight: bold; margin-right: 0.5rem;">Q{{ $qIndex + 1 }}.</span>
                                        {{ $qTrans?->question_text }}
                                    </h3>

                                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                        @php
                                            $shuffledOptions = [];
                                            if ($qTrans?->options) {
                                                foreach ($qTrans->options as $oIndex => $option) {
                                                    $shuffledOptions[] = ['index' => $oIndex, 'text' => $option];
                                                }
                                                mt_srand($question->id);
                                                shuffle($shuffledOptions);
                                                mt_srand();
                                            }
                                        @endphp
                                        @foreach($shuffledOptions as $opt)
                                            @php
                                                $oIndex = $opt['index'];
                                                $option = $opt['text'];
                                                $isSelected = !is_null($savedAnswer) && (int)$savedAnswer === $oIndex;
                                            @endphp
                                            <label class="choice-card">
                                                <input type="radio" name="answers[{{ $question->id }}]" value="{{ $oIndex }}" style="margin-top: 0.2rem; cursor: pointer;" {{ $isSelected ? 'checked' : '' }} onclick="return !isSubmitted;" onkeydown="return !isSubmitted;" required>
                                                <span style="font-size: 0.95rem; color: var(--text-color);">{{ $option }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <!-- Explanation Block (hidden by default) -->
                                    <div class="explanation-box" id="explanation-{{ $question->id }}" style="display: none; margin-top: 1rem; padding: 1rem; background: var(--page-bg); border-left: 3px solid var(--primary-color); border-radius: 4px; font-size: 0.9rem; line-height: 1.5; color: var(--text-muted);">
                                        <strong style="display: block; margin-bottom: 0.25rem;">{{ app()->getLocale() === 'ru' ? 'Объяснение:' : 'Explanation:' }}</strong>
                                        <span class="explanation-text"></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Footer Action Bar -->
                        <div id="quiz-actions" style="margin-top: 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                            <div id="status-message" style="font-size: 0.95rem; font-weight: 600;"></div>
                            
                            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                <form action="{{ route('courses.chapter.reset', ['locale' => app()->getLocale(), 'course_slug' => $course->slug, 'chapter_slug' => $chapter->slug]) }}" method="POST" id="reset-form" style="display: {{ $progress ? 'inline' : 'none' }}; margin: 0;">
                                    @csrf
                                    <button type="submit" class="btn-secondary" id="retake-btn" style="padding: 0.75rem 2rem; display: none;">
                                        {{ app()->getLocale() === 'ru' ? 'Перепройти главу' : 'Retake Chapter' }}
                                    </button>
                                </form>
                                <div id="cooldown-text" style="font-size: 0.9rem; color: var(--text-muted); display: none; align-self: center;"></div>

                                @if(!$progress)
                                    <button type="submit" class="btn-primary glow-button" id="submit-btn" style="padding: 0.75rem 2rem;">
                                        {{ app()->getLocale() === 'ru' ? 'Проверить ответы' : 'Submit Answers' }}
                                    </button>
                                @endif
                                
                                @if($nextChapter)
                                    <a href="{{ route('courses.chapter', ['locale' => app()->getLocale(), 'course_slug' => $course->slug, 'chapter_slug' => $nextChapter->slug]) }}" class="btn-primary" id="next-btn" style="text-decoration: none; padding: 0.75rem 2rem; {{ $progress ? '' : 'display: none;' }}">
                                        {{ app()->getLocale() === 'ru' ? 'Следующая глава' : 'Next Chapter' }} &rarr;
                                    </a>
                                @else
                                    <a href="{{ route('courses.show', ['locale' => app()->getLocale(), 'course_slug' => $course->slug]) }}" class="btn-primary" id="next-btn" style="text-decoration: none; padding: 0.75rem 2rem; {{ $progress ? '' : 'display: none;' }}">
                                        {{ app()->getLocale() === 'ru' ? 'Вернуться к силлабусу' : 'Back to Syllabus' }} &rarr;
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <!-- Achievement Unlock Modal -->
    <div class="achievement-modal" id="achievement-modal" style="display: none; position: fixed; inset: 0; z-index: 99999;">
        <div class="modal-backdrop" style="position: absolute; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px);"></div>
        <div class="modal-wrapper" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 420px;">
            <div class="modal-card glass-card" style="padding: 2rem; text-align: center;">
                <div class="modal-sparkles"></div>
                <span class="modal-emoji" style="font-size: 3.5rem; display: block; margin-bottom: 1rem;">🏆</span>
                <h3 class="modal-title" style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; margin: 0 0 0.5rem 0; color: var(--text-color);">
                    {{ app()->getLocale() === 'ru' ? 'Новое достижение разблокировано!' : 'New Achievement Unlocked!' }}
                </h3>
                <h4 class="modal-badge-name" id="modal-badge-name" style="font-size: 1.25rem; color: var(--primary-color); margin: 0 0 0.5rem 0;">PHP Novice</h4>
                <p class="modal-badge-desc" id="modal-badge-desc" style="font-size: 0.9rem; color: rgba(255,255,255,0.7); margin: 0 0 1.5rem 0;">Scored 50+ total points in quizzes.</p>
                <button type="button" class="btn-primary" style="width: 100%;" onclick="document.getElementById('achievement-modal').style.display = 'none';">
                    {{ app()->getLocale() === 'ru' ? 'Отлично!' : 'Awesome!' }}
                </button>
            </div>
        </div>
    </div>

    <!-- Style overrides for custom layouts & active states -->
    <style>
        .markdown-body h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: var(--text-color);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            padding-bottom: 0.5rem;
        }
        .markdown-body h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            color: var(--text-color);
        }
        .markdown-body p {
            margin-bottom: 1.25rem;
            line-height: 1.7;
        }
        .markdown-body ul, .markdown-body ol {
            margin-bottom: 1.25rem;
            padding-left: 1.5rem;
        }
        .markdown-body li {
            margin-bottom: 0.5rem;
        }
        .markdown-body code {
            font-family: 'Fira Code', 'Courier New', monospace;
            background: rgba(255,255,255,0.05);
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .markdown-body pre {
            background: #0f0f1b;
            padding: 1.25rem;
            border-radius: 8px;
            overflow-x: auto;
            border: 1px solid rgba(255,255,255,0.05);
            margin-bottom: 1.5rem;
        }
        .markdown-body pre code {
            background: transparent;
            padding: 0;
            border-radius: 0;
            color: #e2e8f0;
            font-size: 0.95rem;
        }
        .markdown-body blockquote {
            background: rgba(139, 92, 246, 0.05);
            border-left: 4px solid var(--primary-color);
            padding: 1rem 1.25rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        .markdown-body table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        .markdown-body th, .markdown-body td {
            border: 1px solid rgba(255,255,255,0.08);
            padding: 0.75rem 1rem;
            text-align: left;
        }
        .markdown-body th {
            background: rgba(255,255,255,0.02);
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
        }

    </style>

    <!-- Quiz JS logic -->
    @if($chapter->quiz)
        <script>
            let isSubmitted = @json(!is_null($progress));
            let lastCompletedTimestamp = @json($progress ? (\Carbon\Carbon::parse($progress->completed_at)->timestamp * 1000) : null);
            let retakeTimer = null;

            document.addEventListener('DOMContentLoaded', function() {
                if (isSubmitted) {
                    showPregradedState();
                }
                updateRetakeButton();
            });

            function updateRetakeButton() {
                const retakeBtn = document.getElementById('retake-btn');
                const cooldownText = document.getElementById('cooldown-text');
                if (!retakeBtn) return;
                if (!lastCompletedTimestamp) {
                    retakeBtn.style.display = 'inline-block';
                    if (cooldownText) cooldownText.style.display = 'none';
                    return;
                }

                const now = Date.now();
                const oneDay = 24 * 60 * 60 * 1000;
                const elapsed = now - lastCompletedTimestamp;
                const remaining = oneDay - elapsed;

                if (remaining > 0) {
                    retakeBtn.style.display = 'none';
                    if (cooldownText) {
                        const hours = Math.floor(remaining / (60 * 60 * 1000));
                        const minutes = Math.ceil((remaining % (60 * 60 * 1000)) / (60 * 1000));
                        const textRu = `Перепройти главу можно будет через ${hours} ч. ${minutes} мин.`;
                        const textEn = `You can retake this chapter in ${hours}h ${minutes}m.`;
                        cooldownText.innerText = "{{ app()->getLocale() === 'ru' }}" === "1" ? textRu : textEn;
                        cooldownText.style.display = 'block';
                    }
                    if (!retakeTimer) {
                        retakeTimer = setInterval(updateRetakeButton, 60000);
                    }
                } else {
                    retakeBtn.style.display = 'inline-block';
                    if (cooldownText) cooldownText.style.display = 'none';
                    if (retakeTimer) {
                        clearInterval(retakeTimer);
                        retakeTimer = null;
                    }
                }
            }

            function triggerConfetti(element) {
                const canvas = document.createElement('canvas');
                canvas.style.position = 'fixed';
                canvas.style.top = '0';
                canvas.style.left = '0';
                canvas.style.width = '100vw';
                canvas.style.height = '100vh';
                canvas.style.pointerEvents = 'none';
                canvas.style.zIndex = '99999';
                document.body.appendChild(canvas);

                const ctx = canvas.getContext('2d');
                let width = canvas.width = window.innerWidth;
                let height = canvas.height = window.innerHeight;

                let startX = width / 2;
                let startY = height / 2;
                if (element) {
                    const rect = element.getBoundingClientRect();
                    startX = rect.left + rect.width / 2;
                    startY = rect.top + rect.height / 2;
                }

                const colors = ['#f43f5e', '#3b82f6', '#10b981', '#eab308', '#a855f7', '#f97316'];
                const particles = [];

                for (let i = 0; i < 80; i++) {
                    const angle = Math.random() * Math.PI * 2;
                    const speed = Math.random() * 10 + 5;
                    particles.push({
                        x: startX,
                        y: startY,
                        vx: Math.cos(angle) * speed,
                        vy: Math.sin(angle) * speed - 4,
                        color: colors[Math.floor(Math.random() * colors.length)],
                        radius: Math.random() * 4 + 3,
                        alpha: 1,
                        decay: Math.random() * 0.015 + 0.01
                    });
                }

                function animate() {
                    let alive = false;
                    ctx.clearRect(0, 0, width, height);

                    particles.forEach(p => {
                        if (p.alpha > 0) {
                            p.x += p.vx;
                            p.y += p.vy;
                            p.vy += 0.22;
                            p.vx *= 0.98;
                            p.alpha -= p.decay;

                            ctx.save();
                            ctx.globalAlpha = p.alpha;
                            ctx.fillStyle = p.color;
                            ctx.beginPath();
                            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                            ctx.fill();
                            ctx.restore();

                            if (p.alpha > 0) {
                                alive = true;
                            }
                        }
                    });

                    if (alive) {
                        requestAnimationFrame(animate);
                    } else {
                        canvas.remove();
                    }
                }

                animate();
            }

            function showPregradedState() {
                document.getElementById('chapter-quiz-form').classList.add('submitted');
                // If already submitted, color correctly
                const correctIndexes = {
                    @foreach($chapter->quiz->questions as $question)
                        "{{ $question->id }}": {{ $question->correct_answer_index }},
                    @endforeach
                };
                const explanations = {
                    @foreach($chapter->quiz->questions as $question)
                        "{{ $question->id }}": {!! json_encode($question->explanation) !!},
                    @endforeach
                };

                const savedAnswers = @json($progress ? $progress->answers : []);

                Object.keys(correctIndexes).forEach(qId => {
                    const correctIdx = parseInt(correctIndexes[qId]);
                    const chosenIdx = savedAnswers[qId] !== undefined ? parseInt(savedAnswers[qId]) : -1;
                    
                    const qItem = document.querySelector(`.question-item[data-question-id="${qId}"]`);
                    const options = qItem.querySelectorAll('input[type="radio"]');

                    options.forEach((opt) => {
                        const lbl = opt.parentElement;
                        const optVal = parseInt(opt.value);
                        lbl.classList.add('checked-disabled');
                        
                        if (optVal === correctIdx) {
                            lbl.classList.add('choice-correct');
                        } else if (optVal === chosenIdx) {
                            lbl.classList.add('choice-incorrect');
                        }
                    });

                    // Show explanation
                    const expBox = document.getElementById(`explanation-${qId}`);
                    if (expBox) {
                        expBox.querySelector('.explanation-text').innerText = explanations[qId];
                        expBox.style.display = 'block';
                    }
                });

                // Set status text
                const status = document.getElementById('status-message');
                status.innerText = "{{ app()->getLocale() === 'ru' ? 'Результат сохранен в вашем профиле.' : 'Results stored in your profile.' }}";
                status.style.color = '#10B981';
            }

            function submitQuiz(event) {
                event.preventDefault();
                if (isSubmitted) return;

                const form = document.getElementById('chapter-quiz-form');
                const submitBtn = document.getElementById('submit-btn');
                submitBtn.disabled = true;
                submitBtn.innerText = "{{ app()->getLocale() === 'ru' ? 'Проверка...' : 'Checking...' }}";

                const formData = new FormData(form);
                const answers = {};
                
                // Construct answers object
                for (let [key, value] of formData.entries()) {
                    if (key.startsWith('answers[')) {
                        const qId = key.substring(8, key.length - 1);
                        answers[qId] = value;
                    }
                }

                fetch("{{ route('courses.chapter.complete', ['locale' => app()->getLocale(), 'course_slug' => $course->slug, 'chapter_slug' => $chapter->slug]) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    },
                    body: JSON.stringify({ answers })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        isSubmitted = true;
                        document.getElementById('chapter-quiz-form').classList.add('submitted');

                        const resetForm = document.getElementById('reset-form');
                        if (resetForm) {
                            resetForm.style.display = 'inline';
                        }
                        lastCompletedTimestamp = Date.now();
                        updateRetakeButton();
                        
                        let allCorrect = true;

                        // Highlight answers based on response
                        Object.keys(data.correct_indexes).forEach(qId => {
                            const correctIdx = parseInt(data.correct_indexes[qId]);
                            const chosenIdx = answers[qId] !== undefined ? parseInt(answers[qId]) : -1;
                            const isCorrect = data.correct_answers[qId];
                            
                            if (!isCorrect) {
                                allCorrect = false;
                            }

                            const qItem = document.querySelector(`.question-item[data-question-id="${qId}"]`);
                            const options = qItem.querySelectorAll('input[type="radio"]');

                            options.forEach((opt) => {
                                const lbl = opt.parentElement;
                                const optVal = parseInt(opt.value);
                                lbl.classList.add('checked-disabled');
                                if (optVal === correctIdx) {
                                    lbl.classList.add('choice-correct');
                                } else if (optVal === chosenIdx && !isCorrect) {
                                    lbl.classList.add('choice-incorrect');
                                }
                            });

                            // Display explanation
                            const expBox = document.getElementById(`explanation-${qId}`);
                            if (expBox) {
                                expBox.querySelector('.explanation-text').innerText = data.explanations[qId] || '';
                                expBox.style.display = 'block';
                            }
                        });

                        // Set status message
                        const status = document.getElementById('status-message');
                        status.innerText = `{{ app()->getLocale() === 'ru' ? 'Результат:' : 'Result:' }} ${data.points_scored} / {{ $chapter->quiz->points }} XP`;
                        status.style.color = data.points_scored > 0 ? '#10B981' : '#EF4444';

                        // Show next button and hide submit button
                        submitBtn.style.display = 'none';
                        document.getElementById('next-btn').style.display = 'inline-block';

                        // Trigger Confetti if all correct
                        if (allCorrect) {
                            triggerConfetti(status);
                        }

                        // Handle Badge Unlock modal if unlocked
                        if (data.new_badges && data.new_badges.length > 0) {
                            const badge = data.new_badges[0];
                            document.getElementById('modal-badge-name').innerText = badge.title;
                            document.getElementById('modal-badge-desc').innerText = badge.description;
                            document.getElementById('achievement-modal').style.display = 'block';
                        }
                    } else {
                        alert('Something went wrong. Please try again.');
                        submitBtn.disabled = false;
                        submitBtn.innerText = "{{ app()->getLocale() === 'ru' ? 'Проверить ответы' : 'Submit Answers' }}";
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Request failed. Please check your network connection.');
                    submitBtn.disabled = false;
                    submitBtn.innerText = "{{ app()->getLocale() === 'ru' ? 'Проверить ответы' : 'Submit Answers' }}";
                });
            }
        </script>
    @endif
</x-layout>
