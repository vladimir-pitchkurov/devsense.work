import os
import re

faq_translation_data = {
    'en': 'Frequently Asked Questions',
    'ru': 'Часто задаваемые вопросы',
    'ua': 'Часті запитання',
    'bg': 'Често задавани въпроси',
    'de': 'Häufig gestellte Fragen',
    'fr': 'Foire aux questions',
    'es': 'Preguntas frecuentes',
    'it': 'Domande frequenti'
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, val in faq_translation_data.items():
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
            pattern = r"'faq'\s*=>\s*.*"
            escaped_val = val.replace("'", "\\'")
            if re.search(pattern, block):
                block = re.sub(pattern, f"'faq' => '{escaped_val}',", block)
            else:
                block += f"\n        'faq' => '{escaped_val}',"
            content = content[:pos] + block + content[end_pos:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

print("Added faq translation keys under search block!")
