import os
import re
import subprocess

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"
locales = ['en', 'ru', 'ua', 'bg', 'de', 'fr', 'es', 'it']

for loc in locales:
    file_path = os.path.join(base_dir, loc, "ui.php")
    if not os.path.exists(file_path):
        continue
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # If 'suggestions' => '...' appears right before 'title' =>, fix it back to 'suggestions' => [
    content = re.sub(r"'suggestions'\s*=>\s*'[^']+',\s*('title'\s*=>)", r"'suggestions' => [\n        \1", content)
    
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

print("Applied quick regex fix for suggestions block.")
