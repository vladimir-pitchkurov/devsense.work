import os
import re

authors_show_keys = {
    'en': {
        'authors_show': {
            'anonymous_candidate': 'Anonymous Candidate',
            'anonymous_profile': 'Anonymous Profile',
            'full_details_owner': 'You see full details because you are the owner or admin',
            'seeking_work': 'Seeking Work',
            'passively_seeking': 'Passively Seeking',
            'not_looking': 'Not Looking',
            'contacts_hidden_hint': 'Contact links are hidden by the candidate. You can contact them via personal messages.',
            'work_experience': 'Work Experience',
            'portfolio_projects': 'Portfolio Projects',
        }
    },
    'ru': {
        'authors_show': {
            'anonymous_candidate': 'Анонимный соискатель',
            'anonymous_profile': 'Анонимный профиль',
            'full_details_owner': 'Вы видите полные данные, так как являетесь владельцем или администратором',
            'seeking_work': 'Активно ищу работу',
            'passively_seeking': 'Рассматриваю предложения',
            'not_looking': 'Не ищу работу',
            'contacts_hidden_hint': 'Контактные данные скрыты соискателем. Связаться можно через личные сообщения.',
            'work_experience': 'Опыт работы',
            'portfolio_projects': 'Портфолио проектов',
        }
    },
    'ua': {
        'authors_show': {
            'anonymous_candidate': 'Анонімний кандидат',
            'anonymous_profile': 'Анонімний профіль',
            'full_details_owner': 'Ви бачите повні дані, оскільки є власником або адміністратором',
            'seeking_work': 'Активно шукаю роботу',
            'passively_seeking': 'Розглядаю пропозиції',
            'not_looking': 'Не шукаю роботу',
            'contacts_hidden_hint': 'Контактні дані приховані кандидатом. Зв’язатися можна через особисті повідомлення.',
            'work_experience': 'Досвід роботи',
            'portfolio_projects': 'Портфоліо проектів',
        }
    },
    'bg': {
        'authors_show': {
            'anonymous_candidate': 'Анонимен кандидат',
            'anonymous_profile': 'Анонимен профил',
            'full_details_owner': 'Виждате пълните данни, тъй като сте собственик или администратор',
            'seeking_work': 'Активно търся работа',
            'passively_seeking': 'Разглеждам предложения',
            'not_looking': 'Не търся работа',
            'contacts_hidden_hint': 'Контактните данни са скрити от кандидата.',
            'work_experience': 'Работен опит',
            'portfolio_projects': 'Портфолио от проекти',
        }
    },
    'de': {
        'authors_show': {
            'anonymous_candidate': 'Anonymer Kandidat',
            'anonymous_profile': 'Anonymes Profil',
            'full_details_owner': 'Sie sehen alle Details, da Sie Eigentümer oder Admin sind',
            'seeking_work': 'Suche Arbeit',
            'passively_seeking': 'Offen für Angebote',
            'not_looking': 'Suche derzeit nicht',
            'contacts_hidden_hint': 'Kontaktdaten sind vom Kandidaten verborgen.',
            'work_experience': 'Berufserfahrung',
            'portfolio_projects': 'Portfolio-Projekte',
        }
    },
    'fr': {
        'authors_show': {
            'anonymous_candidate': 'Candidat anonyme',
            'anonymous_profile': 'Profil anonyme',
            'full_details_owner': 'Vous voyez tous les détails car vous êtes le propriétaire ou l\'administrateur',
            'seeking_work': 'En recherche active',
            'passively_seeking': 'À l\'écoute du marché',
            'not_looking': 'Pas en recherche',
            'contacts_hidden_hint': 'Les coordonnées sont masquées par le candidat.',
            'work_experience': 'Expérience professionnelle',
            'portfolio_projects': 'Projets de portfolio',
        }
    },
    'es': {
        'authors_show': {
            'anonymous_candidate': 'Candidato anónimo',
            'anonymous_profile': 'Perfil anónimo',
            'full_details_owner': 'Ves todos los detalles porque eres el propietario o administrador',
            'seeking_work': 'Buscando empleo',
            'passively_seeking': 'Abierto a ofertas',
            'not_looking': 'No busco empleo',
            'contacts_hidden_hint': 'Los datos de contacto están ocultos por el candidato.',
            'work_experience': 'Experiencia laboral',
            'portfolio_projects': 'Proyectos de portafolio',
        }
    },
    'it': {
        'authors_show': {
            'anonymous_candidate': 'Candidato anonimo',
            'anonymous_profile': 'Profilo anonimo',
            'full_details_owner': 'Vedi tutti i dettagli perché sei il proprietario o l\'amministratore',
            'seeking_work': 'In cerca di lavoro',
            'passively_seeking': 'Valuto proposte',
            'not_looking': 'Non cerco lavoro',
            'contacts_hidden_hint': 'I contatti sono nascosti dal candidato.',
            'work_experience': 'Esperienza lavorativa',
            'portfolio_projects': 'Progetti portfolio',
        }
    }
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, data in authors_show_keys.items():
    file_path = os.path.join(base_dir, lang, "ui.php")
    if not os.path.exists(file_path):
        continue
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    for section_key, section_dict in data.items():
        pattern = rf"'{section_key}'\s*=>\s*\["
        if re.search(pattern, content):
            for k, v in section_dict.items():
                k_pattern = rf"'{re.escape(k)}'\s*=>\s*.*"
                escaped_v = v.replace("'", "\\'")
                if re.search(k_pattern, content):
                    content = re.sub(k_pattern, f"'{k}' => '{escaped_v}',", content)
                else:
                    inject_pos = re.search(pattern, content).end()
                    content = content[:inject_pos] + f"\n        '{k}' => '{escaped_v}'," + content[inject_pos:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

views_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\resources\views"
feat_index = os.path.join(views_dir, "features", "index.blade.php")
if os.path.exists(feat_index):
    with open(feat_index, 'r', encoding='utf-8') as f:
        c = f.read()
    c = c.replace('title="{{ __(\'ui.features.title\') }}"', ':title="__(\'ui.features.title\')"')
    with open(feat_index, 'w', encoding='utf-8') as f:
        f.write(c)

print("Populated authors_show keys and fixed features/index x-layout binding!")
