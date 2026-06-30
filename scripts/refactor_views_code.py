import os

views_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\resources\views"

# Quizzes index
quizzes_index = os.path.join(views_dir, "quizzes", "index.blade.php")
if os.path.exists(quizzes_index):
    with open(quizzes_index, 'r', encoding='utf-8') as f:
        c = f.read()
    c = c.replace("app()->getLocale() === 'ru' ? 'Квизы и Геймификация | DevSense' : 'Interview Quizzes & Gamification | DevSense'", "__('ui.quizzes.index_title')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Проверьте свои технические знания по PHP, Laravel и архитектуре. Набирайте очки и открывайте достижения!' : 'Test your technical knowledge of PHP, Laravel, and software architecture. Score points and unlock badges!'", "__('ui.quizzes.index_desc')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Квизы и Геймификация' : 'Interview Quizzes & Gamification'", "__('ui.quizzes.hero_title')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Интерактивные тесты для подготовки к техническим собеседованиям. Прокачайте навыки, зарабатывайте баллы и открывайте бейджи.' : 'Interactive challenges to prepare for technical interviews. Level up your skills, score points, and unlock achievements.'", "__('ui.quizzes.hero_lead')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Баллов' : 'Points'", "__('ui.quizzes.points')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Прогресс достижений' : 'Achievement Progress'", "__('ui.quizzes.achievement_progress')")
    c = c.replace("app()->getLocale() === 'ru' ? 'баллов' : 'points'", "__('ui.quizzes.points_short')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Максимальный ранг!' : 'Max Rank Unlocked!'", "__('ui.quizzes.max_rank')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Следующий бейдж:' : 'Next badge:'", "__('ui.quizzes.next_badge')")
    c = c.replace("app()->getLocale() === 'ru' ? 'нужно' : 'requires'", "__('ui.quizzes.requires')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Ваши бейджи' : 'Your Badges'", "__('ui.quizzes.your_badges')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Пройдите первый квиз, чтобы разблокировать награду!' : 'Complete your first quiz to unlock a badge!'", "__('ui.quizzes.first_quiz_hint')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Подтвердите email, чтобы сохранять прогресс!' : 'Verify your email to save progress!'", "__('ui.quizzes.verify_email_hint')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Подтвердить имейл' : 'Verify Email'", "__('ui.quizzes.verify_email_btn')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Хотите отслеживать свои результаты?' : 'Want to track your progress?'", "__('ui.quizzes.track_progress_title')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Авторизуйтесь, чтобы копить баллы за верные ответы, разблокировать престижные бейджи и войти в глобальный рейтинг разработчиков!' : 'Sign in to save your quiz answers, accumulate XP points, unlock rare achievement badges, and show off your technical expertise!'", "__('ui.quizzes.track_progress_lead')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Войти' : 'Sign In'", "__('ui.quizzes.login_btn')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Регистрация' : 'Register'", "__('ui.quizzes.register_btn')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Доступные квизы' : 'Available Quizzes'", "__('ui.quizzes.available_quizzes')")
    c = c.replace("app()->getLocale() === 'ru' ? 'вопросов' : 'questions'", "__('ui.quizzes.questions_count')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Пройдено' : 'Completed'", "__('ui.quizzes.completed_status')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Пройти квиз' : 'Start Quiz'", "__('ui.quizzes.start_quiz_btn')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Пройти снова' : 'Retake Quiz'", "__('ui.quizzes.retake_quiz_btn')")
    with open(quizzes_index, 'w', encoding='utf-8') as f:
        f.write(c)
    print("Refactored quizzes/index.blade.php")

# Features index
features_index = os.path.join(views_dir, "features", "index.blade.php")
if os.path.exists(features_index):
    with open(features_index, 'r', encoding='utf-8') as f:
        c = f.read()
    c = c.replace("app()->getLocale() === 'ru' ? 'Голосование за фичи | DevSense' : 'Roadmap Feature Voting | DevSense'", "__('ui.features.title')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Дорожная карта развития проекта' : 'Project Development Roadmap'", "__('ui.features.hero_title')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Голосуйте за функции, которые вы хотите увидеть на платформе в первую очередь. Мы развиваемся вместе с сообществом!' : 'Vote for the features you want to see implemented next. We build and prioritize based on community feedback!'", "__('ui.features.hero_lead')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Сила вашего голоса' : 'Your Voting Power'", "__('ui.features.voting_power')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Каждые 100 XP увеличивают силу вашего голоса на +1 пункт.' : 'Every 100 XP points increase your vote weight by +1.'", "__('ui.features.voting_power_lead')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Накоплено XP: ' : 'Your current XP: '", "__('ui.features.current_xp') . ' '")
    c = c.replace("app()->getLocale() === 'ru' ? 'Вы не авторизованы' : 'You are not signed in'", "__('ui.features.not_signed_in')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Войдите, чтобы проголосовать' : 'Sign in to cast your votes'", "__('ui.features.sign_in_to_vote')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Популярность' : 'Popularity'", "__('ui.features.popularity')")
    c = c.replace("app()->getLocale() === 'ru' ? 'чел.' : 'voters'", "__('ui.features.voters')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Убрать голос' : 'Retract Vote'", "__('ui.features.retract_vote')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Голосовать' : 'Vote'", "__('ui.features.vote')")
    with open(features_index, 'w', encoding='utf-8') as f:
        f.write(c)
    print("Refactored features/index.blade.php")
