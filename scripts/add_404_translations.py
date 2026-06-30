import os
import re

seo_404_data = {
    'en': {
        'page_not_found': 'Page Not Found',
        'back_to_home': 'Back to Home',
        'breadcrumb_features': 'Roadmap',
    },
    'ru': {
        'page_not_found': 'Страница не найдена',
        'back_to_home': 'На главную',
        'breadcrumb_features': 'Дорожная карта',
    },
    'ua': {
        'page_not_found': 'Сторінка не знайдена',
        'back_to_home': 'На головну',
        'breadcrumb_features': 'Дорожня карта',
    },
    'bg': {
        'page_not_found': 'Страницата не е намерена',
        'back_to_home': 'Към главната страница',
        'breadcrumb_features': 'План',
    },
    'de': {
        'page_not_found': 'Seite nicht gefunden',
        'back_to_home': 'Zur Startseite',
        'breadcrumb_features': 'Roadmap',
    },
    'fr': {
        'page_not_found': 'Page non trouvée',
        'back_to_home': 'Retour à l\'accueil',
        'breadcrumb_features': 'Feuille de route',
    },
    'es': {
        'page_not_found': 'Página no encontrada',
        'back_to_home': 'Volver al inicio',
        'breadcrumb_features': 'Mapa de ruta',
    },
    'it': {
        'page_not_found': 'Pagina non trovata',
        'back_to_home': 'Torna alla home',
        'breadcrumb_features': 'Roadmap',
    }
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, data in seo_404_data.items():
    file_path = os.path.join(base_dir, lang, "ui.php")
    if not os.path.exists(file_path):
        continue
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    pos = content.find("'seo' => [")
    if pos != -1:
        end_pos = content.find("],", pos)
        if end_pos != -1:
            block = content[pos:end_pos]
            for k, val in data.items():
                pattern = rf"'{k}'\s*=>\s*.*"
                escaped_val = val.replace("'", "\\'")
                if re.search(pattern, block):
                    block = re.sub(pattern, f"'{k}' => '{escaped_val}',", block)
                else:
                    block += f"\n        '{k}' => '{escaped_val}',"
            content = content[:pos] + block + content[end_pos:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

print("Added 404 and features breadcrumb translations under seo block!")
