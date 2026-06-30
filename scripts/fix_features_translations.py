import os
import re

features_data = {
    'en': {
        'title': 'Roadmap Feature Voting | DevSense',
        'hero_title': 'Project Development Roadmap',
        'hero_lead': 'Vote for the features you want to see implemented next. We build and prioritize based on community feedback!',
    },
    'ru': {
        'title': 'Голосование за фичи | DevSense',
        'hero_title': 'Дорожная карта развития проекта',
        'hero_lead': 'Голосуйте за функции, которые вы хотите увидеть на платформе в первую очередь. Мы развиваемся вместе с сообществом!',
    },
    'ua': {
        'title': 'Голосування за фічі | DevSense',
        'hero_title': 'Дорожня карта розвитку проекту',
        'hero_lead': 'Голосуйте за функції, які ви хочете побачити на платформі в першу чергу. Ми розвиваємося разом із спільнотою!',
    },
    'bg': {
        'title': 'Гласуване за функции | DevSense',
        'hero_title': 'План за развитие на проекта',
        'hero_lead': 'Гласувайте за функциите, които искате да видите първо!',
    },
    'de': {
        'title': 'Funktions-Abstimmung | DevSense',
        'hero_title': 'Projekt-Roadmap',
        'hero_lead': 'Stimmen Sie für die Funktionen ab, die Sie sich als Nächstes wünschen!',
    },
    'fr': {
        'title': 'Vote de fonctionnalités | DevSense',
        'hero_title': 'Feuille de route du projet',
        'hero_lead': 'Votez pour les fonctionnalités que vous souhaitez voir en premier !',
    },
    'es': {
        'title': 'Votación de características | DevSense',
        'hero_title': 'Mapa de desarrollo del proyecto',
        'hero_lead': '¡Vota por las características que quieres ver a continuación!',
    },
    'it': {
        'title': 'Votazione funzionalità | DevSense',
        'hero_title': 'Roadmap di sviluppo del progetto',
        'hero_lead': 'Vota le funzionalità che desideri vedere per prime!',
    }
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, data in features_data.items():
    file_path = os.path.join(base_dir, lang, "ui.php")
    if not os.path.exists(file_path):
        continue
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    pos = content.find("'features' => [")
    if pos != -1:
        end_pos = content.find("],", pos)
        if end_pos != -1:
            block = content[pos:end_pos]
            for k, v in data.items():
                escaped_v = v.replace("'", "\\'")
                pattern = rf"'{k}'\s*=>\s*.*"
                if re.search(pattern, block):
                    block = re.sub(pattern, f"'{k}' => '{escaped_v}',", block)
            content = content[:pos] + block + content[end_pos:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

print("Fixed features section in all language files.")
