import os
import re

show_keys = {
    'en': {
        'quizzes': {
            'back_to_quizzes': 'Back to Quizzes',
            'loading': 'Loading...',
            'completed_title': 'Quiz Completed!',
            'completed_desc': 'Great job! Here is a summary of your performance:',
            'correct_answers': 'Correct Answers',
            'xp_earned': 'XP Points Earned',
            'review_questions': 'Review Questions',
            'quizzes_list': 'Quizzes List',
            'check_answer': 'Check Answer',
            'show_correct_answer': 'Show Correct Answer',
            'finish_quiz': 'Finish Quiz',
            'next_question': 'Next Question',
            'submitting': 'Submitting...',
            'processing': 'Processing answers...',
            'login_to_save_hint': 'Please sign in to submit your quiz and save points.',
            'question': 'Question',
            'of': 'of',
            'score': 'Score:',
            'available_challenges': 'Available Challenges',
        }
    },
    'ru': {
        'quizzes': {
            'back_to_quizzes': 'Назад к квизам',
            'loading': 'Загрузка...',
            'completed_title': 'Тест завершен!',
            'completed_desc': 'Вы отлично справились! Вот ваши результаты:',
            'correct_answers': 'Правильные ответы',
            'xp_earned': 'Получено очков',
            'review_questions': 'Обзор ответов',
            'quizzes_list': 'К списку квизов',
            'check_answer': 'Проверить',
            'show_correct_answer': 'Показать правильный ответ',
            'finish_quiz': 'Показать результаты',
            'next_question': 'Дальше',
            'submitting': 'Подсчет результатов...',
            'processing': 'Пожалуйста, подождите...',
            'login_to_save_hint': 'Пожалуйста, войдите в систему, чтобы сохранить свои результаты.',
            'question': 'Вопрос',
            'of': 'из',
            'score': 'Счёт:',
            'available_challenges': 'Доступные испытания',
        }
    },
    'ua': {
        'quizzes': {
            'back_to_quizzes': 'Назад до тестів',
            'loading': 'Завантаження...',
            'completed_title': 'Тест завершено!',
            'completed_desc': 'Чудова робота! Ось ваші результати:',
            'correct_answers': 'Вірні відповіді',
            'xp_earned': 'Отримано балів',
            'review_questions': 'Огляд відповідей',
            'quizzes_list': 'До списку тестів',
            'check_answer': 'Перевірити',
            'show_correct_answer': 'Показати вірну відповідь',
            'finish_quiz': 'Показати результати',
            'next_question': 'Далі',
            'submitting': 'Підрахунок результатів...',
            'processing': 'Будь ласка, зачекайте...',
            'login_to_save_hint': 'Будь ласка, увійдіть до системи, щоб зберегти свої результати.',
            'question': 'Питання',
            'of': 'з',
            'score': 'Рахунок:',
            'available_challenges': 'Доступні випробування',
        }
    },
    'bg': {
        'quizzes': {
            'back_to_quizzes': 'Назад към тестовете',
            'loading': 'Зареждане...',
            'completed_title': 'Тестът е завършен!',
            'completed_desc': 'Страхотна работа! Ето резултатите ви:',
            'correct_answers': 'Правилни отговори',
            'xp_earned': 'Спечелени XP',
            'review_questions': 'Преглед на въпросите',
            'quizzes_list': 'Списък с тестове',
            'check_answer': 'Провери',
            'show_correct_answer': 'Покажи правилния отговор',
            'finish_quiz': 'Покажи резултатите',
            'next_question': 'Следващ въпрос',
            'submitting': 'Обработка...',
            'processing': 'Моля, изчакайте...',
            'login_to_save_hint': 'Моля, влезте в профила си, за да запазите резултатите.',
            'question': 'Въпрос',
            'of': 'от',
            'score': 'Резултат:',
            'available_challenges': 'Достъпни предизвикателства',
        }
    },
    'de': {
        'quizzes': {
            'back_to_quizzes': 'Zurück zu den Quizzes',
            'loading': 'Laden...',
            'completed_title': 'Quiz abgeschlossen!',
            'completed_desc': 'Gute Arbeit! Hier ist Ihre Zusammenfassung:',
            'correct_answers': 'Richtige Antworten',
            'xp_earned': 'Verdiente XP',
            'review_questions': 'Fragen überprüfen',
            'quizzes_list': 'Quiz-Liste',
            'check_answer': 'Prüfen',
            'show_correct_answer': 'Richtige Antwort anzeigen',
            'finish_quiz': 'Quiz beenden',
            'next_question': 'Nächste Frage',
            'submitting': 'Wird gesendet...',
            'processing': 'Bitte warten...',
            'login_to_save_hint': 'Bitte melden Sie sich an, um Ihre Ergebnisse zu speichern.',
            'question': 'Frage',
            'of': 'von',
            'score': 'Ergebnis:',
            'available_challenges': 'Verfügbare Herausforderungen',
        }
    },
    'fr': {
        'quizzes': {
            'back_to_quizzes': 'Retour aux quiz',
            'loading': 'Chargement...',
            'completed_title': 'Quiz terminé !',
            'completed_desc': 'Beau travail ! Voici un résumé de vos résultats :',
            'correct_answers': 'Réponses correctes',
            'xp_earned': 'XP gagnés',
            'review_questions': 'Réviser les questions',
            'quizzes_list': 'Liste des quiz',
            'check_answer': 'Vérifier',
            'show_correct_answer': 'Afficher la bonne réponse',
            'finish_quiz': 'Terminer le quiz',
            'next_question': 'Question suivante',
            'submitting': 'Soumission...',
            'processing': 'Veuillez patienter...',
            'login_to_save_hint': 'Veuillez vous connecter pour sauvegarder vos résultats.',
            'question': 'Question',
            'of': 'sur',
            'score': 'Score :',
            'available_challenges': 'Défis disponibles',
        }
    },
    'es': {
        'quizzes': {
            'back_to_quizzes': 'Volver a los cuestionarios',
            'loading': 'Cargando...',
            'completed_title': '¡Cuestionario completado!',
            'completed_desc': '¡Buen trabajo! Aquí tienes un resumen:',
            'correct_answers': 'Respuestas correctas',
            'xp_earned': 'XP ganados',
            'review_questions': 'Repasar preguntas',
            'quizzes_list': 'Lista de cuestionarios',
            'check_answer': 'Comprobar',
            'show_correct_answer': 'Mostrar respuesta correcta',
            'finish_quiz': 'Finalizar cuestionario',
            'next_question': 'Siguiente pregunta',
            'submitting': 'Enviando...',
            'processing': 'Por favor espera...',
            'login_to_save_hint': 'Por favor inicia sesión para guardar tus resultados.',
            'question': 'Pregunta',
            'of': 'de',
            'score': 'Puntuación:',
            'available_challenges': 'Desafíos disponibles',
        }
    },
    'it': {
        'quizzes': {
            'back_to_quizzes': 'Torna ai quiz',
            'loading': 'Caricamento...',
            'completed_title': 'Quiz completato!',
            'completed_desc': 'Ottimo lavoro! Ecco un riepilogo delle tue prestazioni:',
            'correct_answers': 'Risposte corrette',
            'xp_earned': 'XP guadagnati',
            'review_questions': 'Rivedi le domande',
            'quizzes_list': 'Lista dei quiz',
            'check_answer': 'Verifica',
            'show_correct_answer': 'Mostra risposta corretta',
            'finish_quiz': 'Termina quiz',
            'next_question': 'Domanda successiva',
            'submitting': 'Invio...',
            'processing': 'Attendere prego...',
            'login_to_save_hint': 'Per favore accedi per salvare i tuoi risultati.',
            'question': 'Domanda',
            'of': 'di',
            'score': 'Punteggio:',
            'available_challenges': 'Sfide disponibili',
        }
    }
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, data in show_keys.items():
    file_path = os.path.join(base_dir, lang, "ui.php")
    if not os.path.exists(file_path):
        continue
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    for k, v in data['quizzes'].items():
        k_pattern = rf"'{re.escape(k)}'\s*=>\s*.*"
        escaped_v = v.replace("'", "\\'")
        if not re.search(k_pattern, content):
            pos = content.find("'quizzes' => [")
            if pos != -1:
                content = content[:pos+14] + f"\n        '{k}' => '{escaped_v}'," + content[pos+14:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
print("Updated quiz show keys across all languages!")

views_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\resources\views"

# Refactor quizzes/show.blade.php
q_show = os.path.join(views_dir, "quizzes", "show.blade.php")
if os.path.exists(q_show):
    with open(q_show, 'r', encoding='utf-8') as f:
        c = f.read()
    c = c.replace("app()->getLocale() === 'ru' ? 'Назад к квизам' : 'Back to Quizzes'", "__('ui.quizzes.back_to_quizzes')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Загрузка...' : 'Loading...'", "__('ui.quizzes.loading')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Тест завершен!' : 'Quiz Completed!'", "__('ui.quizzes.completed_title')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Вы отлично справились! Вот ваши результаты:' : 'Great job! Here is a summary of your performance:'", "__('ui.quizzes.completed_desc')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Правильные ответы' : 'Correct Answers'", "__('ui.quizzes.correct_answers')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Получено очков' : 'XP Points Earned'", "__('ui.quizzes.xp_earned')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Обзор ответов' : 'Review Questions'", "__('ui.quizzes.review_questions')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Пройти заново' : 'Retake Quiz'", "__('ui.quizzes.retake_quiz_btn')")
    c = c.replace("app()->getLocale() === 'ru' ? 'К списку квизов' : 'Quizzes List'", "__('ui.quizzes.quizzes_list')")
    c = c.replace("app()->getLocale() === 'ru' ? 'На главную' : 'Home'", "__('ui.nav.home')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Проверить' : 'Check Answer'", "__('ui.quizzes.check_answer')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Новое достижение разблокировано!' : 'New Achievement Unlocked!'", "__('ui.courses.achievement_unlocked')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Отлично!' : 'Awesome!'", "__('ui.courses.awesome')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Показать правильный ответ' : 'Show Correct Answer'", "__('ui.quizzes.show_correct_answer')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Показать результаты' : 'Finish Quiz'", "__('ui.quizzes.finish_quiz')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Дальше' : 'Next Question'", "__('ui.quizzes.next_question')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Подсчет результатов...' : 'Submitting...'", "__('ui.quizzes.submitting')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Пожалуйста, подождите...' : 'Processing answers...'", "__('ui.quizzes.processing')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Пожалуйста, войдите в систему, чтобы сохранить свои результаты.' : 'Please sign in to submit your quiz and save points.'", "__('ui.quizzes.login_to_save_hint')")
    with open(q_show, 'w', encoding='utf-8') as f:
        f.write(c)
    print("Refactored quizzes/show.blade.php")

# Refactor remaining strings in quizzes/index.blade.php
q_idx = os.path.join(views_dir, "quizzes", "index.blade.php")
if os.path.exists(q_idx):
    with open(q_idx, 'r', encoding='utf-8') as f:
        c = f.read()
    c = c.replace("app()->getLocale() === 'ru' ? 'Доступные испытания' : 'Available Challenges'", "__('ui.quizzes.available_challenges')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Счёт:' : 'Score:'", "__('ui.quizzes.score')")
    with open(q_idx, 'w', encoding='utf-8') as f:
        f.write(c)
    print("Refactored remaining strings in quizzes/index.blade.php")

# Refactor courses/chapter.blade.php
c_chap = os.path.join(views_dir, "courses", "chapter.blade.php")
if os.path.exists(c_chap):
    with open(c_chap, 'r', encoding='utf-8') as f:
        c = f.read()
    c = c.replace("app()->getLocale() === 'ru' ? 'К силлабусу курса' : 'Back to Syllabus'", "__('ui.courses.back_to_syllabus')")
    c = c.replace("@if(app()->getLocale() === 'ru')\n                <!-- Title & Header -->\n                <div style=\"margin-bottom: 1rem;\">\n                    <h1 style=\"font-family: 'Outfit', sans-serif; font-size: 2.25rem; margin: 0 0 0.5rem 0; color: var(--text-color);\">\n                        {{ $chapter->translate()?->title }}\n                    </h1>\n                </div>\n            @else\n                <div style=\"margin-bottom: 1rem;\">\n                    <h1 style=\"font-family: 'Outfit', sans-serif; font-size: 2.25rem; margin: 0 0 0.5rem 0; color: var(--text-color);\">\n                        {{ $chapter->translate()?->title }}\n                    </h1>\n                </div>\n            @endif", "<div style=\"margin-bottom: 1rem;\">\n                <h1 style=\"font-family: 'Outfit', sans-serif; font-size: 2.25rem; margin: 0 0 0.5rem 0; color: var(--text-color);\">\n                    {{ $chapter->translate()?->title }}\n                </h1>\n            </div>")
    c = c.replace("app()->getLocale() === 'ru' ? 'Проверка знаний' : 'Knowledge Check'", "__('ui.courses.knowledge_check')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Объяснение:' : 'Explanation:'", "__('ui.courses.explanation')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Перепройти главу' : 'Retake Chapter'", "__('ui.courses.retake_chapter')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Проверить ответы' : 'Submit Answers'", "__('ui.courses.submit_answers')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Следующая глава' : 'Next Chapter'", "__('ui.courses.next_chapter')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Вернуться к силлабусу' : 'Back to Syllabus'", "__('ui.courses.back_to_syllabus')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Новое достижение разблокировано!' : 'New Achievement Unlocked!'", "__('ui.courses.achievement_unlocked')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Отлично!' : 'Awesome!'", "__('ui.courses.awesome')")
    c = c.replace("cooldownText.innerText = \"{{ app()->getLocale() === 'ru' }}\" === \"1\" ? textRu : textEn;", "const template = \"{{ __('ui.courses.cooldown_msg') }}\"; cooldownText.innerText = template.replace('{hours}', hours).replace('{minutes}', minutes);")
    c = c.replace("app()->getLocale() === 'ru' ? 'Результат сохранен в вашем профиле.' : 'Results stored in your profile.'", "__('ui.courses.result_stored')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Проверка...' : 'Checking...'", "__('ui.courses.checking')")
    c = c.replace("app()->getLocale() === 'ru' ? 'Результат:' : 'Result:'", "__('ui.courses.result_score')")
    with open(c_chap, 'w', encoding='utf-8') as f:
        f.write(c)
    print("Refactored courses/chapter.blade.php")
