import os
import re

final_keys = {
    'en': {
        'welcome': {
            'ecosystem_lead': 'A complete ecosystem for PHP and Backend developers. Read deep-dive guides, test your knowledge in quizzes, and suggest site improvements.',
        },
        'quizzes': {
            'verify_unverified_hint': 'Please verify your email address to unlock badges and achievement progress.',
            'completed_badge': 'Completed',
            'start_quiz': 'Start Quiz',
        }
    },
    'ru': {
        'welcome': {
            'ecosystem_lead': 'Единая экосистема для PHP и Backend разработчиков. Читайте технические руководства, проверяйте знания на квизах и предлагайте улучшения.',
        },
        'quizzes': {
            'verify_unverified_hint': 'Пожалуйста, подтвердите ваш имейл, чтобы разблокировать бейджи и прогресс достижений.',
            'completed_badge': 'Пройден',
            'start_quiz': 'Начать тест',
        }
    },
    'ua': {
        'welcome': {
            'ecosystem_lead': 'Єдина екосистема для PHP та Backend розробників. Читайте технічні посібники, перевіряйте знання на тестах та пропонуйте покращення.',
        },
        'quizzes': {
            'verify_unverified_hint': 'Будь ласка, підтвердіть ваш имейл, щоб розблокувати бейджі та прогрес досягнень.',
            'completed_badge': 'Пройдено',
            'start_quiz': 'Розпочати тест',
        }
    },
    'bg': {
        'welcome': {
            'ecosystem_lead': 'Пълна екосистема за PHP и Backend разработчици. Четете ръководства и решавайте тестове.',
        },
        'quizzes': {
            'verify_unverified_hint': 'Моля, потвърдете имейла си, за да отключите значки.',
            'completed_badge': 'Завършено',
            'start_quiz': 'Започни теста',
        }
    },
    'de': {
        'welcome': {
            'ecosystem_lead': 'Ein komplettes Ökosystem für PHP- und Backend-Entwickler.',
        },
        'quizzes': {
            'verify_unverified_hint': 'Bitte bestätigen Sie Ihre E-Mail-Adresse, um Abzeichen freizuschalten.',
            'completed_badge': 'Abgeschlossen',
            'start_quiz': 'Quiz starten',
        }
    },
    'fr': {
        'welcome': {
            'ecosystem_lead': 'Un écosystème complet pour les développeurs PHP et Backend.',
        },
        'quizzes': {
            'verify_unverified_hint': 'Veuillez vérifier votre adresse e-mail pour débloquer des badges.',
            'completed_badge': 'Terminé',
            'start_quiz': 'Commencer le quiz',
        }
    },
    'es': {
        'welcome': {
            'ecosystem_lead': 'Un ecosistema completo para desarrolladores de PHP y Backend.',
        },
        'quizzes': {
            'verify_unverified_hint': 'Por favor verifica tu correo electrónico para desbloquear insignias.',
            'completed_badge': 'Completado',
            'start_quiz': 'Iniciar cuestionario',
        }
    },
    'it': {
        'welcome': {
            'ecosystem_lead': 'Un ecosistema completo per sviluppatori PHP e Backend.',
        },
        'quizzes': {
            'verify_unverified_hint': 'Verifica il tuo indirizzo email per sbloccare i badge.',
            'completed_badge': 'Completato',
            'start_quiz': 'Inizia quiz',
        }
    }
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, data in final_keys.items():
    file_path = os.path.join(base_dir, lang, "ui.php")
    if not os.path.exists(file_path):
        continue
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    for section_key, section_dict in data.items():
        pattern = rf"'{section_key}'\s*=>\s*\["
        if re.search(pattern, content):
            for k, v in section_dict.items():
                k_pattern = rf"'{re.escape(k)}'\s*=>\s*.*"
                escaped_v = v.replace("'", "\\'")
                if re.search(k_pattern, content):
                    content = re.sub(k_pattern, f"'{k}' => '{escaped_v}',", content)
                else:
                    inject_pos = re.search(pattern, content).end()
                    content = content[:inject_pos] + f"\n        '{k}' => '{escaped_v}'," + content[inject_pos:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

views_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\resources\views"

# welcome.blade.php
wel = os.path.join(views_dir, "welcome.blade.php")
if os.path.exists(wel):
    with open(wel, 'r', encoding='utf-8') as f:
        c = f.read()
    c = re.sub(r"\{\{\s*app\(\)->getLocale\(\)\s*===\s*'ru'\s*\?\s*'Единая экосистема для PHP и Backend разработчиков\. Читайте технические руководства, проверяйте знания на квизах и предлагайте улучшения\.'\s*:\s*'A complete ecosystem for PHP and Backend developers\. Read deep-dive guides, test your knowledge in quizzes, and suggest site improvements\.'\s*\}\}", "{{ __('ui.welcome.ecosystem_lead') }}", c)
    with open(wel, 'w', encoding='utf-8') as f:
        f.write(c)

# quizzes/index.blade.php
qidx = os.path.join(views_dir, "quizzes", "index.blade.php")
if os.path.exists(qidx):
    with open(qidx, 'r', encoding='utf-8') as f:
        c = f.read()
    c = re.sub(r"\{\{\s*app\(\)->getLocale\(\)\s*===\s*'ru'\s*\?\s*'Пожалуйста, подтвердите ваш имейл, чтобы разблокировать бейджи и прогресс достижений\.'\s*:\s*'Please verify your email address to unlock badges and achievement progress\.'\s*\}\}", "{{ __('ui.quizzes.verify_unverified_hint') }}", c)
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Пройден' : 'Completed' }}", "{{ __('ui.quizzes.completed_badge') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Начать тест' : 'Start Quiz' }}", "{{ __('ui.quizzes.start_quiz') }}")
    with open(qidx, 'w', encoding='utf-8') as f:
        f.write(c)

print("Cleaned up final remaining view strings!")
