import os
import re

read_more_data = {
    'en': 'Read',
    'ru': 'Читать',
    'ua': 'Читати',
    'bg': 'Прочети',
    'de': 'Lesen',
    'fr': 'Lire',
    'es': 'Leer',
    'it': 'Leggi'
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, val in read_more_data.items():
    file_path = os.path.join(base_dir, lang, "ui.php")
    if not os.path.exists(file_path):
        continue
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    pos = content.find("'search' => [")
    if pos != -1:
        end_pos = content.find("],", pos)
        if end_pos != -1:
            block = content[pos:end_pos]
            pattern = r"'read_more'\s*=>\s*.*"
            if re.search(pattern, block):
                block = re.sub(pattern, f"'read_more' => '{val}',", block)
            else:
                block += f"\n        'read_more' => '{val}',"
            content = content[:pos] + block + content[end_pos:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

print("Added read_more translations under search key!")
