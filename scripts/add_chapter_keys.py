import os
import re

chapter_keys = {
    'en': {
        'cooldown_msg': 'You can retake this chapter in {hours}h {minutes}m.',
    },
    'ru': {
        'cooldown_msg': 'Перепройти главу можно будет через {hours} ч. {minutes} мин.',
    },
    'ua': {
        'cooldown_msg': 'Перепройти главу можна буде через {hours} год. {minutes} хв.',
    },
    'bg': {
        'cooldown_msg': 'Можете да повторите главата след {hours} ч. {minutes} мин.',
    },
    'de': {
        'cooldown_msg': 'Sie können dieses Kapitel in {hours} Std. {minutes} Min. wiederholen.',
    },
    'fr': {
        'cooldown_msg': 'Vous pourrez refaire ce chapitre dans {hours}h {minutes}m.',
    },
    'es': {
        'cooldown_msg': 'Puedes repetir este capítulo en {hours}h {minutes}m.',
    },
    'it': {
        'cooldown_msg': 'Puoi rifare questo capitolo tra {hours}h {minutes}m.',
    }
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, data in chapter_keys.items():
    file_path = os.path.join(base_dir, lang, "ui.php")
    if not os.path.exists(file_path):
        continue
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    for k, v in data.items():
        k_pattern = rf"'{re.escape(k)}'\s*=>\s*.*"
        escaped_v = v.replace("'", "\\'")
        if not re.search(k_pattern, content):
            pos = content.find("'courses' => [")
            if pos != -1:
                content = content[:pos+14] + f"\n        '{k}' => '{escaped_v}'," + content[pos+14:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
print("Added chapter keys!")
