import os
import re

extra_keys = {
    'en': {
        'courses': {
            'open_syllabus': 'Open Syllabus',
            'login_required': 'Login required to unlock this course',
            'course_progress': 'Course Progress',
            'chapters': 'chapters',
        }
    },
    'ru': {
        'courses': {
            'open_syllabus': 'Открыть силлабус',
            'login_required': 'Для доступа необходимо авторизоваться',
            'course_progress': 'Прогресс прохождения',
            'chapters': 'глав',
        }
    },
    'ua': {
        'courses': {
            'open_syllabus': 'До силлабусу курсу',
            'login_required': 'Для доступу необхідно авторизуватися',
            'course_progress': 'Прогрес проходження',
            'chapters': 'глав',
        }
    },
    'bg': {
        'courses': {
            'open_syllabus': 'Към програмата',
            'login_required': 'Изисква се вход за отключване',
            'course_progress': 'Прогрес на курса',
            'chapters': 'глави',
        }
    },
    'de': {
        'courses': {
            'open_syllabus': 'Lehrplan öffnen',
            'login_required': 'Anmeldung erforderlich',
            'course_progress': 'Kursfortschritt',
            'chapters': 'Kapitel',
        }
    },
    'fr': {
        'courses': {
            'open_syllabus': 'Ouvrir le programme',
            'login_required': 'Connexion requise pour débloquer',
            'course_progress': 'Progression du cours',
            'chapters': 'chapitres',
        }
    },
    'es': {
        'courses': {
            'open_syllabus': 'Abrir programa',
            'login_required': 'Se requiere iniciar sesión',
            'course_progress': 'Progreso del curso',
            'chapters': 'capítulos',
        }
    },
    'it': {
        'courses': {
            'open_syllabus': 'Apri programma',
            'login_required': 'Accesso richiesto per sbloccare',
            'course_progress': 'Progresso del corso',
            'chapters': 'capitoli',
        }
    }
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, data in extra_keys.items():
    file_path = os.path.join(base_dir, lang, "ui.php")
    if not os.path.exists(file_path):
        continue
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    for k, v in data['courses'].items():
        k_pattern = rf"'{re.escape(k)}'\s*=>\s*.*"
        escaped_v = v.replace("'", "\\'")
        if not re.search(k_pattern, content):
            pos = content.find("'courses' => [")
            if pos != -1:
                content = content[:pos+14] + f"\n        '{k}' => '{escaped_v}'," + content[pos+14:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
print("Added extra course keys!")
