import os
import re

jobs_data = {
    'en': {
        'card_jobs_title': 'Careers & Jobs',
        'card_jobs_excerpt': 'Find your next role as a PHP, Laravel, or Backend developer. Localized opportunities with clean coding standards.',
    },
    'ru': {
        'card_jobs_title': 'Вакансии и Карьера',
        'card_jobs_excerpt': 'Найдите следующую роль PHP, Laravel или Backend разработчика. Локализованные возможности с высокими стандартами кода.',
    },
    'ua': {
        'card_jobs_title': 'Вакансії та Кар\'єра',
        'card_jobs_excerpt': 'Знайдіть свою наступну роль PHP, Laravel або Backend розробника. Локалізовані можливості з високими стандартами коду.',
    },
    'bg': {
        'card_jobs_title': 'Кариери и свободни позиции',
        'card_jobs_excerpt': 'Намерете следващата си роля като PHP, Laravel или Backend разработчик.',
    },
    'de': {
        'card_jobs_title': 'Karriere & Jobs',
        'card_jobs_excerpt': 'Finden Sie Ihre nächste Stelle als PHP-, Laravel- oder Backend-Entwickler.',
    },
    'fr': {
        'card_jobs_title': 'Carrières & Emplois',
        'card_jobs_excerpt': 'Trouvez votre prochain poste en tant que développeur PHP, Laravel ou Backend.',
    },
    'es': {
        'card_jobs_title': 'Carrera y Empleos',
        'card_jobs_excerpt': 'Encuentra tu próximo puesto como desarrollador PHP, Laravel o Backend.',
    },
    'it': {
        'card_jobs_title': 'Carriere & Lavoro',
        'card_jobs_excerpt': 'Trova il tuo prossimo ruolo come sviluppatore PHP, Laravel o Backend.',
    }
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, data in jobs_data.items():
    file_path = os.path.join(base_dir, lang, "ui.php")
    if not os.path.exists(file_path):
        continue
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # Find the welcome block
    pos = content.find("'welcome' => [")
    if pos != -1:
        end_pos = content.find("],", pos)
        if end_pos != -1:
            block = content[pos:end_pos]
            for k, v in data.items():
                escaped_v = v.replace("'", "\\'")
                pattern = rf"'{k}'\s*=>\s*.*"
                if re.search(pattern, block):
                    block = re.sub(pattern, f"'{k}' => '{escaped_v}',", block)
                else:
                    block += f"\n        '{k}' => '{escaped_v}',"
            content = content[:pos] + block + content[end_pos:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

print("Synchronized card_jobs_title and card_jobs_excerpt across all locales!")
