<x-layout 
    :title="($quiz->translate()?->title ?? 'Technical Quiz') . ' | DevSense'"
    :description="$quiz->translate()?->description ?? ''"
>
    <div class="quiz-show-container">
        <!-- Back Button -->
        <nav class="quiz-nav-back">
            <a href="{{ route('quizzes.index') }}" class="back-link">
                ← {{ app()->getLocale() === 'ru' ? 'Назад к квизам' : 'Back to Quizzes' }}
            </a>
        </nav>

        <!-- Main Quiz Glass Box -->
        <div class="quiz-box glass-card" id="quiz-box">
            <!-- Header Progress -->
            <div class="quiz-box-header">
                <h2 class="quiz-title" style="font-family: 'Outfit', sans-serif;">
                    {{ $quiz->translate()?->title }}
                </h2>
                <div class="question-progress" id="question-progress">
                    {{ app()->getLocale() === 'ru' ? 'Загрузка...' : 'Loading...' }}
                </div>
            </div>

            <!-- Quiz Step Track Line -->
            <div class="quiz-step-track">
                <div class="quiz-step-fill" id="quiz-step-fill" style="width: 0%;"></div>
            </div>

            <!-- Quiz Active Content Area -->
            <div class="quiz-box-body">
                <!-- Question Container -->
                <div class="question-container" id="question-container">
                    <h3 class="question-text" id="question-text" style="font-family: 'Outfit', sans-serif;"></h3>
                    
                    <!-- Code block helper if question contains code -->
                    <div class="question-code-block" id="question-code-block" style="display: none;">
                        <pre><code></code></pre>
                    </div>

                    <!-- Choices Options Grid -->
                    <div class="choices-grid" id="choices-grid"></div>
                </div>

                <!-- Result Container (hidden by default) -->
                <div class="result-container" id="result-container" style="display: none;">
                    <div class="result-celebration">
                        <span class="celebration-emoji" id="celebration-emoji">🎉</span>
                        <h3 class="result-headline" style="font-family: 'Outfit', sans-serif;" id="result-headline">
                            {{ app()->getLocale() === 'ru' ? 'Тест завершен!' : 'Quiz Completed!' }}
                        </h3>
                        <p class="result-sub">
                            {{ app()->getLocale() === 'ru' ? 'Вы отлично справились! Вот ваши результаты:' : 'Great job! Here is a summary of your performance:' }}
                        </p>
                    </div>

                    <!-- Score stats cards -->
                    <div class="result-stats">
                        <div class="stat-card">
                            <span class="stat-lbl">{{ app()->getLocale() === 'ru' ? 'Правильные ответы' : 'Correct Answers' }}</span>
                            <span class="stat-val" id="stat-correct-count">0/0</span>
                        </div>
                        <div class="stat-card accent">
                            <span class="stat-lbl">{{ app()->getLocale() === 'ru' ? 'Получено очков' : 'XP Points Earned' }}</span>
                            <span class="stat-val" id="stat-xp-earned">+0 XP</span>
                        </div>
                    </div>

                    <!-- Review Section -->
                    <div class="review-section">
                        <h4 class="review-title" style="font-family: 'Outfit', sans-serif;">
                            {{ app()->getLocale() === 'ru' ? 'Обзор ответов' : 'Review Questions' }}
                        </h4>
                        <div class="review-list" id="review-list"></div>
                    </div>

                    <!-- Action buttons at the bottom of the results page -->
                    <div class="result-actions" style="margin-top: 3rem; display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                        <button type="button" class="btn-primary" id="retake-quiz-btn">
                            {{ app()->getLocale() === 'ru' ? 'Пройти заново' : 'Retake Quiz' }}
                        </button>
                        <a href="{{ route('quizzes.index') }}" class="btn-secondary">
                            {{ app()->getLocale() === 'ru' ? 'К списку квизов' : 'Quizzes List' }}
                        </a>
                        <a href="{{ route('home') }}" class="btn-secondary">
                            {{ app()->getLocale() === 'ru' ? 'На главную' : 'Home' }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Footer Action Bar -->
            <div class="quiz-box-footer" id="quiz-box-footer">
                <div class="feedback-hint" id="feedback-hint"></div>
                <button type="button" class="btn-primary glow-button" id="footer-action-btn" disabled>
                    {{ app()->getLocale() === 'ru' ? 'Проверить' : 'Check Answer' }}
                </button>
            </div>
        </div>
    </div>

    <!-- Achievement Unlock Modal -->
    <div class="achievement-modal" id="achievement-modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-wrapper">
            <div class="modal-card glass-card">
                <div class="modal-sparkles"></div>
                <span class="modal-emoji">🏆</span>
                <h3 class="modal-title" style="font-family: 'Outfit', sans-serif;">
                    {{ app()->getLocale() === 'ru' ? 'Новое достижение разблокировано!' : 'New Achievement Unlocked!' }}
                </h3>
                <h4 class="modal-badge-name" id="modal-badge-name">PHP Novice</h4>
                <p class="modal-badge-desc" id="modal-badge-desc">Scored 50+ total points in quizzes.</p>
                <button type="button" class="btn-primary" onclick="document.getElementById('achievement-modal').style.display = 'none';">
                    {{ app()->getLocale() === 'ru' ? 'Отлично!' : 'Awesome!' }}
                </button>
            </div>
        </div>
    </div>

    <!-- JS Logic -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const quizData = {
            id: {{ $quiz->id }},
            slug: "{{ $quiz->slug }}",
            points: {{ $quiz->points }},
            questions: [
                @foreach($quiz->questions as $question)
                @php
                    $qTrans = $question->translate();
                @endphp
                {
                    id: {{ $question->id }},
                    text: {!! json_encode($qTrans?->question_text ?? '') !!},
                    options: {!! json_encode($qTrans?->options) !!},
                    points: {{ $question->points }},
                    @auth
                    correctIndex: {{ $question->correct_answer_index }},
                    explanation: {!! json_encode($question->explanation ?? '') !!}
                    @endauth
                },
                @endforeach
            ]
        };

        let lastCompletedTimestamp = @json($lastCompletedTimestamp);
        let retakeTimer = null;
        const retakeBtn = document.getElementById('retake-quiz-btn');

        function updateRetakeButton() {
            if (!retakeBtn) return;
            if (!lastCompletedTimestamp) {
                retakeBtn.disabled = false;
                retakeBtn.innerHTML = "{{ app()->getLocale() === 'ru' ? 'Пройти заново' : 'Retake Quiz' }}";
                return;
            }

            const now = Date.now();
            const oneHour = 60 * 60 * 1000;
            const elapsed = now - lastCompletedTimestamp;
            const remaining = oneHour - elapsed;

            if (remaining > 0) {
                retakeBtn.disabled = true;
                const minutes = Math.ceil(remaining / (60 * 1000));
                retakeBtn.innerHTML = "{{ app()->getLocale() === 'ru' ? 'Пройти заново (через ' : 'Retake Quiz (in ' }}" + minutes + " {{ app()->getLocale() === 'ru' ? 'мин)' : 'min)' }}";
                
                if (!retakeTimer) {
                    retakeTimer = setInterval(updateRetakeButton, 10000);
                }
            } else {
                retakeBtn.disabled = false;
                retakeBtn.innerHTML = "{{ app()->getLocale() === 'ru' ? 'Пройти заново' : 'Retake Quiz' }}";
                if (retakeTimer) {
                    clearInterval(retakeTimer);
                    retakeTimer = null;
                }
            }
        }

        if (retakeBtn) {
            retakeBtn.addEventListener('click', function () {
                clearProgress();
                window.location.reload();
            });
        }

        const isLoggedIn = @json(auth()->check());
        const loginUrl = "{{ route('login.locale') }}";
        const registerUrl = "{{ route('register.locale') }}";
        const PROGRESS_KEY = `quiz_progress_${quizData.slug}`;
        const PROGRESS_TTL = 24 * 60 * 60 * 1000; // 24 hours

        function saveProgressToLocalStorage(index, answers) {
            try {
                localStorage.setItem(PROGRESS_KEY, JSON.stringify({
                    questionIndex: index,
                    answers: answers,
                    savedAt: Date.now()
                }));
            } catch (e) {
                console.error('Failed to save progress to localStorage', e);
            }
        }

        function loadProgressFromLocalStorage() {
            try {
                const raw = localStorage.getItem(PROGRESS_KEY);
                if (!raw) return null;
                const data = JSON.parse(raw);
                if (Date.now() - data.savedAt > PROGRESS_TTL) {
                    localStorage.removeItem(PROGRESS_KEY);
                    return null;
                }
                return data;
            } catch (e) {
                return null;
            }
        }

        function clearProgress() {
            try {
                localStorage.removeItem(PROGRESS_KEY);
            } catch (e) {}
        }

        let currentQuestionIndex = 0;
        let userAnswers = {};

        const serverProgress = @json($inProgressData);
        const localProgress = loadProgressFromLocalStorage();

        if (isLoggedIn) {
            if (localProgress && (!serverProgress || localProgress.questionIndex > serverProgress.question_index)) {
                currentQuestionIndex = localProgress.questionIndex;
                userAnswers = localProgress.answers || {};
                
                fetch("{{ route('quizzes.progress', ['slug' => $quiz->slug]) }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({
                        question_index: currentQuestionIndex,
                        answers: userAnswers
                    })
                }).catch(err => console.error("Failed to sync progress on load", err));
            } else if (serverProgress) {
                currentQuestionIndex = serverProgress.question_index;
                userAnswers = serverProgress.answers || {};
            }
            clearProgress();
        } else {
            if (localProgress) {
                currentQuestionIndex = localProgress.questionIndex;
                userAnswers = localProgress.answers || {};
            }
        }

        if (currentQuestionIndex >= quizData.questions.length) {
            currentQuestionIndex = 0;
            userAnswers = {};
        }

        let selectedOptionIndex = null;
        let isChecked = false;

        const questionProgress = document.getElementById('question-progress');
        const quizStepFill = document.getElementById('quiz-step-fill');
        const questionText = document.getElementById('question-text');
        const choicesGrid = document.getElementById('choices-grid');
        const footerActionBtn = document.getElementById('footer-action-btn');
        const feedbackHint = document.getElementById('feedback-hint');
        const quizBoxFooter = document.getElementById('quiz-box-footer');

        function loadQuestion() {
            selectedOptionIndex = null;
            isChecked = false;
            feedbackHint.style.display = 'none';
            feedbackHint.className = 'feedback-hint';
            feedbackHint.innerHTML = '';
            
            footerActionBtn.disabled = true;
            footerActionBtn.innerHTML = "{{ app()->getLocale() === 'ru' ? 'Проверить' : 'Check Answer' }}";

            const question = quizData.questions[currentQuestionIndex];
            
            // Set Progress
            const total = quizData.questions.length;
            questionProgress.innerHTML = `{{ app()->getLocale() === 'ru' ? 'Вопрос' : 'Question' }} ${currentQuestionIndex + 1} {{ app()->getLocale() === 'ru' ? 'из' : 'of' }} ${total}`;
            
            const progressPct = ((currentQuestionIndex) / total) * 100;
            quizStepFill.style.width = `${progressPct}%`;

            // Set Text
            questionText.innerHTML = question.text;

            // Render Choices
            choicesGrid.innerHTML = '';
            question.options.forEach((option, idx) => {
                const choiceBtn = document.createElement('button');
                choiceBtn.type = 'button';
                choiceBtn.className = 'choice-card';
                choiceBtn.innerHTML = `
                    <span class="choice-marker">${String.fromCharCode(65 + idx)}</span>
                    <span class="choice-text">${option}</span>
                `;
                choiceBtn.addEventListener('click', () => selectOption(idx));
                choicesGrid.appendChild(choiceBtn);
            });
        }

        function selectOption(index) {
            if (isChecked) return;
            selectedOptionIndex = index;
            footerActionBtn.disabled = false;

            const cards = choicesGrid.querySelectorAll('.choice-card');
            cards.forEach((card, idx) => {
                if (idx === index) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
        }

        function triggerConfetti(element) {
            if (!element) return;
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

            const rect = element.getBoundingClientRect();
            const startX = rect.left + rect.width / 2;
            const startY = rect.top + rect.height / 2;

            const colors = ['#f43f5e', '#3b82f6', '#10b981', '#eab308', '#a855f7', '#f97316'];
            const particles = [];

            for (let i = 0; i < 60; i++) {
                const angle = Math.random() * Math.PI * 2;
                const speed = Math.random() * 8 + 4;
                particles.push({
                    x: startX,
                    y: startY,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed - 3,
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

        function checkCurrentAnswer() {
            const question = quizData.questions[currentQuestionIndex];
            const cards = choicesGrid.querySelectorAll('.choice-card');
            
            cards.forEach(card => {
                card.disabled = true;
                card.classList.add('checked-disabled');
            });

            if (question.correctIndex === undefined) {
                cards[selectedOptionIndex]?.classList.add('choice-selected-checked');
                showRevealButton();
            } else {
                const isCorrect = (selectedOptionIndex === question.correctIndex);
                const correctCard = cards[question.correctIndex];
                if (correctCard) {
                    correctCard.classList.add('choice-correct');
                }
                if (!isCorrect) {
                    cards[selectedOptionIndex]?.classList.add('choice-incorrect');
                } else {
                    triggerConfetti(correctCard);
                }
                if (question.explanation) {
                    showExplanation(question.explanation);
                }
            }
        }

        function showRevealButton() {
            feedbackHint.innerHTML = '';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-reveal-answer';
            btn.innerHTML = "{{ app()->getLocale() === 'ru' ? 'Показать правильный ответ' : 'Show Correct Answer' }}";
            btn.addEventListener('click', showLoginCTA);
            feedbackHint.appendChild(btn);
            feedbackHint.style.display = 'block';
        }

        function showLoginCTA() {
            const isRu = "{{ app()->getLocale() === 'ru' }}";
            feedbackHint.innerHTML = `
                <div class="quiz-login-cta">
                    <p>🔒 ${isRu ? 'Войдите, чтобы видеть правильные ответы и подробные объяснения' : 'Sign in to see correct answers and detailed explanations'}</p>
                    <div class="quiz-cta-actions">
                        <a href="${loginUrl}" class="admin-btn admin-btn--primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none; border-radius: 6px; font-family: 'Outfit', sans-serif;">
                            ${isRu ? 'Войти' : 'Sign in'}
                        </a>
                        <a href="${registerUrl}" class="admin-btn admin-btn--secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none; border-radius: 6px; font-family: 'Outfit', sans-serif;">
                            ${isRu ? 'Регистрация' : 'Register'}
                        </a>
                    </div>
                </div>
            `;
            feedbackHint.style.display = 'block';
        }

        function showExplanation(text) {
            const isRu = "{{ app()->getLocale() === 'ru' }}";
            feedbackHint.innerHTML = `
                <div class="quiz-explanation">
                    <strong>${isRu ? 'Объяснение:' : 'Explanation:'}</strong> ${text}
                </div>
            `;
            feedbackHint.style.display = 'block';
        }

        footerActionBtn.addEventListener('click', function () {
            if (!isChecked) {
                isChecked = true;
                userAnswers[quizData.questions[currentQuestionIndex].id] = selectedOptionIndex;
                
                checkCurrentAnswer();

                const isLast = (currentQuestionIndex === quizData.questions.length - 1);
                footerActionBtn.innerHTML = isLast 
                    ? "{{ app()->getLocale() === 'ru' ? 'Показать результаты' : 'Finish Quiz' }}"
                    : "{{ app()->getLocale() === 'ru' ? 'Дальше' : 'Next Question' }}";

                const nextIndex = currentQuestionIndex + 1;
                if (isLoggedIn) {
                    fetch("{{ route('quizzes.progress', ['slug' => $quiz->slug]) }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": "{{ csrf_token() }}"
                        },
                        body: JSON.stringify({
                            question_index: nextIndex,
                            answers: userAnswers
                        })
                    }).catch(err => console.error("Failed to save progress", err));
                } else {
                    saveProgressToLocalStorage(nextIndex, userAnswers);
                }
            } else {
                currentQuestionIndex++;
                if (currentQuestionIndex < quizData.questions.length) {
                    loadQuestion();
                } else {
                    submitQuiz();
                }
            }
        });

        function submitQuiz() {
            // Loader State
            questionProgress.innerHTML = "{{ app()->getLocale() === 'ru' ? 'Подсчет результатов...' : 'Submitting...' }}";
            quizStepFill.style.width = "100%";
            choicesGrid.innerHTML = '';
            questionText.innerHTML = "{{ app()->getLocale() === 'ru' ? 'Пожалуйста, подождите...' : 'Processing answers...' }}";
            footerActionBtn.style.display = 'none';

            clearProgress();

            fetch("{{ route('quizzes.complete', ['slug' => $quiz->slug]) }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                body: JSON.stringify({
                    answers: userAnswers
                })
            })
            .then(res => {
                if (!res.ok) {
                    if (res.status === 401) {
                        throw new Error("unauthenticated");
                    }
                    throw new Error("server_error");
                }
                return res.json();
            })
            .then(data => {
                if (data.is_guest) {
                    showGuestCTA(data.points_scored);
                } else {
                    showResults(data);
                }
            })
            .catch(err => {
                console.error(err);
                if (err.message === "unauthenticated") {
                    questionText.innerHTML = `
                        <div class="auth-error-state">
                            <p>{{ app()->getLocale() === 'ru' ? 'Пожалуйста, войдите в систему, чтобы сохранить свои результаты.' : 'Please sign in to submit your quiz and save points.' }}</p>
                            <a href="{{ route('login.locale') }}" class="btn-primary">{{ __('ui.nav.login') }}</a>
                        </div>
                    `;
                } else {
                    questionText.innerHTML = "{{ app()->getLocale() === 'ru' ? 'Произошла ошибка при отправке теста. Попробуйте еще раз.' : 'An error occurred. Please try again.' }}";
                }
            });
        }

        function showGuestCTA(pointsScored) {
            document.getElementById('question-container').style.display = 'none';
            quizBoxFooter.style.display = 'none';

            const appLocale = "{{ app()->getLocale() }}";
            const resultContainer = document.getElementById('result-container');
            
            resultContainer.innerHTML = `
                <div class="result-celebration" style="padding: 2.5rem 1.5rem; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); border-radius: 12px; margin-top: 1rem; text-align: center; box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);">
                    <span class="celebration-emoji" style="font-size: 4rem; display: block; margin-bottom: 1rem; filter: drop-shadow(0 4px 10px rgba(99, 102, 241, 0.3));">🎯</span>
                    <h3 class="result-headline" style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; color: var(--text-color); margin: 0 0 0.75rem;">
                        ${pointsScored > 0 
                            ? (appLocale === 'ru' ? 'Вы набрали ' + pointsScored + ' XP!' : 'You scored ' + pointsScored + ' XP!')
                            : (appLocale === 'ru' ? 'Тест завершен!' : 'Quiz Completed!')
                        }
                    </h3>
                    <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.6; max-width: 500px; margin: 0 auto 2rem;">
                        ${appLocale === 'ru' 
                            ? 'Зарегистрируйтесь или войдите в систему, чтобы сохранить свои результаты, увидеть правильные ответы с подробными объяснениями и разблокировать достижения!' 
                            : 'Sign up or sign in now to save your results, view correct answers with detailed explanations, and unlock achievements!'
                        }
                    </p>
                    <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                        <a href="{{ route('register.locale') }}" class="admin-btn admin-btn--primary" style="padding: 0.75rem 1.75rem; font-weight: 600; text-decoration: none; border-radius: 8px; font-family: 'Outfit', sans-serif;">
                            ${appLocale === 'ru' ? 'Регистрация' : 'Sign Up'}
                        </a>
                        <a href="{{ route('login.locale') }}" class="admin-btn admin-btn--secondary" style="padding: 0.75rem 1.75rem; font-weight: 600; text-decoration: none; border-radius: 8px; font-family: 'Outfit', sans-serif;">
                            ${appLocale === 'ru' ? 'Войти' : 'Sign In'}
                        </a>
                    </div>
                </div>
            `;
            resultContainer.style.display = 'block';
        }

        function showResults(data) {
            document.getElementById('question-container').style.display = 'none';
            document.getElementById('result-container').style.display = 'block';
            quizBoxFooter.style.display = 'none';

            // Set Stats
            const totalQuestions = quizData.questions.length;
            const correctCount = Object.values(data.correct_answers).filter(v => v === true).length;
            document.getElementById('stat-correct-count').innerHTML = `${correctCount}/${totalQuestions}`;
            document.getElementById('stat-xp-earned').innerHTML = `+${data.points_scored} XP`;

            // Change Celebration Emoji
            const emoji = document.getElementById('celebration-emoji');
            if (correctCount === totalQuestions) {
                emoji.innerHTML = "👑";
            } else if (correctCount === 0) {
                emoji.innerHTML = "😅";
            } else {
                emoji.innerHTML = "🎉";
            }

            // Render review details
            const reviewList = document.getElementById('review-list');
            reviewList.innerHTML = '';

            quizData.questions.forEach(q => {
                const isCorrect = data.correct_answers[q.id];
                const correctIdx = data.correct_indexes[q.id];
                const userIdx = userAnswers[q.id];
                const explanation = data.explanations[q.id];

                const reviewItem = document.createElement('div');
                reviewItem.className = `review-item ${isCorrect ? 'correct' : 'incorrect'}`;
                
                let answersHTML = '';
                q.options.forEach((opt, idx) => {
                    let optClass = '';
                    if (idx === correctIdx) optClass = 'correct-opt';
                    else if (idx === userIdx && !isCorrect) optClass = 'incorrect-opt';

                    answersHTML += `
                        <div class="review-opt ${optClass}">
                            <span class="opt-bullet">${String.fromCharCode(65 + idx)}</span>
                            <span>${opt}</span>
                        </div>
                    `;
                });

                reviewItem.innerHTML = `
                    <h5 class="review-question-text">${q.text}</h5>
                    <div class="review-opts-container">${answersHTML}</div>
                    ${explanation ? `
                        <div class="review-explanation">
                            <strong>{{ app()->getLocale() === 'ru' ? 'Объяснение:' : 'Explanation:' }}</strong> ${explanation}
                        </div>
                    ` : ''}
                `;
                reviewList.appendChild(reviewItem);
            });

            // Start cooldown timer
            lastCompletedTimestamp = Date.now();
            updateRetakeButton();

            // Trigger Badges Modal
            if (data.new_badges && data.new_badges.length > 0) {
                const badge = data.new_badges[0];
                document.getElementById('modal-badge-name').innerText = badge.title;
                document.getElementById('modal-badge-desc').innerText = badge.description;
                document.getElementById('achievement-modal').style.display = 'flex';
            }
        }

        // Initialize
        updateRetakeButton();
        const showResultsData = @json($showResultsData);
        if (showResultsData) {
            showResults(showResultsData);
        } else {
            loadQuestion();
        }
    });
    </script>
</x-layout>
