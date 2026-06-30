import os
import re

jobs_section_data = {
    'en': {
        'index_title': 'Careers & Open Openings | DevSense',
        'index_desc': 'Explore localized PHP, Laravel, and Backend developer jobs. Apply to verified high-quality openings.',
        'apply_btn': 'Apply Now',
        'remote': 'Remote',
        'fulltime': 'Full-time',
    },
    'ru': {
        'index_title': 'Вакансии и карьера | DevSense',
        'index_desc': 'Исследуйте вакансии для PHP, Laravel и Backend разработчиков. Подавайте заявки на проверенные вакансии.',
        'apply_btn': 'Подать заявку',
        'remote': 'Удаленно',
        'fulltime': 'Полная занятость',
    },
    'ua': {
        'index_title': 'Вакансії та кар\'єра | DevSense',
        'index_desc': 'Досліджуйте вакансії для PHP, Laravel та Backend розробників. Подавайте заявки на перевірені вакансії.',
        'apply_btn': 'Подати заявку',
        'remote': 'Віддалено',
        'fulltime': 'Повна зайнятість',
    },
    'bg': {
        'index_title': 'Кариери и свободни позиции | DevSense',
        'index_desc': 'Разгледайте свободните позиции за PHP, Laravel и Backend разработчици.',
        'apply_btn': 'Кандидатствай сега',
        'remote': 'Дистанционно',
        'fulltime': 'Пълно работно време',
    },
    'de': {
        'index_title': 'Karriere & Stellenangebote | DevSense',
        'index_desc': 'Entdecken Sie Stellenangebote für PHP-, Laravel- und Backend-Entwickler.',
        'apply_btn': 'Jetzt bewerben',
        'remote': 'Remote',
        'fulltime': 'Vollzeit',
    },
    'fr': {
        'index_title': 'Carrières & Offres d\'emploi | DevSense',
        'index_desc': 'Découvrez des offres d\'emploi pour les développeurs PHP, Laravel et Backend.',
        'apply_btn': 'Postuler',
        'remote': 'Télétravail',
        'fulltime': 'Temps plein',
    },
    'es': {
        'index_title': 'Carreras y Vacantes | DevSense',
        'index_desc': 'Explore ofertas de empleo para desarrolladores PHP, Laravel y Backend.',
        'apply_btn': 'Postularse',
        'remote': 'Remoto',
        'fulltime': 'Tiempo completo',
    },
    'it': {
        'index_title': 'Carriere e Posizioni aperte | DevSense',
        'index_desc': 'Esplora le offerte di lavoro per sviluppatori PHP, Laravel e Backend.',
        'apply_btn': 'Candidati ora',
        'remote': 'Da remoto',
        'fulltime': 'Tempo pieno',
    }
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, data in jobs_section_data.items():
    file_path = os.path.join(base_dir, lang, "ui.php")
    if not os.path.exists(file_path):
        continue
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    pos = content.find("'jobs' => [")
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

print("Synchronized jobs section keys across all locales!")
