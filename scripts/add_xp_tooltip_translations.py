import os
import re

xp_tooltip_data = {
    'en': 'Earn XP by completing quizzes and courses. Every 100 XP increases your roadmap vote weight by +1.',
    'ru': 'Зарабатывайте XP, проходя квизы и курсы. Каждые 100 XP увеличивают силу вашего голоса в дорожной карте на +1.',
    'ua': 'Заробляйте XP, проходячи квізи та курси. Кожні 100 XP збільшують силу вашого голосу в дорожній карті на +1.',
    'bg': 'Печелете XP чрез решаване на тестове и курсове. Всеки 100 XP увеличават силата на гласа ви в плана с +1.',
    'de': 'Sammeln Sie XP, indem Sie Quizzes und Kurse absolvieren. Alle 100 XP erhöhen das Gewicht Ihrer Stimme in der Roadmap um +1.',
    'fr': 'Gagnez des XP en complétant des quiz et des cours. Chaque tranche de 100 XP augmente le poids de votre vote dans la feuille de route de +1.',
    'es': 'Gane XP completando cuestionarios y cursos. Cada 100 XP aumenta el peso de su voto en la hoja de ruta en +1.',
    'it': 'Guadagna XP completando quiz e corsi. Ogni 100 XP aumenta il peso del tuo voto nella roadmap di +1.'
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, val in xp_tooltip_data.items():
    file_path = os.path.join(base_dir, lang, "ui.php")
    if not os.path.exists(file_path):
        continue
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    pos = content.find("'dashboard' => [")
    if pos != -1:
        end_pos = content.find("],", pos)
        if end_pos != -1:
            block = content[pos:end_pos]
            pattern = r"'xp_info_tooltip'\s*=>\s*.*"
            escaped_val = val.replace("'", "\\'")
            if re.search(pattern, block):
                block = re.sub(pattern, f"'xp_info_tooltip' => '{escaped_val}',", block)
            else:
                block += f"\n        'xp_info_tooltip' => '{escaped_val}',"
            content = content[:pos] + block + content[end_pos:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

print("Added xp_info_tooltip translations under dashboard key!")
