import os
import re

welcome_keys = {
    'en': {
        'welcome': {
            'next_gen': 'Next-gen DevSense Platform',
            'browse_catalog': 'Browse Catalog',
            'try_quizzes': 'Try Quizzes',
            'guides': 'Guides',
            'technical_articles': 'Technical Articles',
            'fresh_guides': 'Fresh guides on PHP runtime, databases, microservices, and high-load architecture.',
            'all_articles': 'All Articles',
            'interview_quizzes': 'Interview Quizzes',
            'quizzes_lead': 'Check your readiness for interview questions, gain XP, and unlock rare badges.',
            'all_quizzes': 'All Quizzes',
            'ideas': 'Ideas',
            'community_board': 'Community Board',
            'suggestions_lead': 'Suggest topics and vote on feature ideas submitted by other developers.',
            'votes': 'votes',
            'view_suggestions': 'View Suggestions',
        },
        'suggestions': {
            'title': 'Community Suggestions | DevSense',
            'hero_title': 'Community Suggestions',
            'hero_lead': 'Suggest new tutorial topics or request platform enhancements. Upvote ideas you support!',
            'submit_title': 'Submit a Suggestion',
            'placeholder': 'Describe your suggestion in detail (min 10 characters)...',
            'submit_btn': 'Submit Idea',
            'sign_in_hint': 'Sign in to submit a suggestion or cast your votes.',
            'empty': 'No suggestions submitted yet. Be the first!',
            'for_guide': 'For guide:',
            'comments': 'comments',
            'add_comment_placeholder': 'Add comment...',
            'post': 'Post',
        }
    },
    'ru': {
        'welcome': {
            'next_gen': 'Новое поколение DevSense',
            'browse_catalog': 'Перейти в каталог',
            'try_quizzes': 'Пройти квиз',
            'guides': 'Гайды',
            'technical_articles': 'Технические статьи',
            'fresh_guides': 'Актуальные статьи и руководства по PHP, базам данных и архитектуре высоконагруженных систем.',
            'all_articles': 'Все статьи',
            'interview_quizzes': 'Интервью Квизы',
            'quizzes_lead': 'Проверьте себя, зарабатывайте очки опыта (XP) и соревнуйтесь в таблице лидеров.',
            'all_quizzes': 'Все квизы',
            'ideas': 'Идеи',
            'community_board': 'Предложения',
            'suggestions_lead': 'Предлагайте темы статей и функции платформы. Поддерживайте чужие идеи голосами.',
            'votes': 'голосов',
            'view_suggestions': 'Все предложения',
        },
        'suggestions': {
            'title': 'Предложения сообщества | DevSense',
            'hero_title': 'Предложения сообщества',
            'hero_lead': 'Предлагайте новые темы для статей или улучшения функций платформы. Голосуйте за лучшие идеи!',
            'submit_title': 'Поделитесь своей идеей',
            'placeholder': 'Опишите ваше предложение подробно (минимум 10 символов)...',
            'submit_btn': 'Отправить предложение',
            'sign_in_hint': 'Войдите на сайт, чтобы отправить предложение или проголосовать.',
            'empty': 'Предложений пока нет. Будьте первыми!',
            'for_guide': 'Для статьи:',
            'comments': 'коммент.',
            'add_comment_placeholder': 'Добавить комментарий...',
            'post': 'Отправить',
        }
    },
    'ua': {
        'welcome': {
            'next_gen': 'Нове покоління DevSense',
            'browse_catalog': 'Перейти до каталогу',
            'try_quizzes': 'Пройти тест',
            'guides': 'Гайди',
            'technical_articles': 'Технічні статті',
            'fresh_guides': 'Актуальні статті та посібники з PHP, баз даних та архітектури високонавантажених систем.',
            'all_articles': 'Усі статті',
            'interview_quizzes': 'Інтерв’ю Тести',
            'quizzes_lead': 'Перевірте себе, заробляйте бали досвіду (XP) та змагайтеся у таблиці лідерів.',
            'all_quizzes': 'Усі тести',
            'ideas': 'Ідеї',
            'community_board': 'Пропозиції',
            'suggestions_lead': 'Пропонуйте теми статей та функції платформи. Підтримуйте чужі ідеї голосами.',
            'votes': 'голосів',
            'view_suggestions': 'Усі пропозиції',
        },
        'suggestions': {
            'title': 'Пропозиції спільноти | DevSense',
            'hero_title': 'Пропозиції спільноти',
            'hero_lead': 'Пропонуйте нові теми для статей або покращення функцій платформи. Голосуйте за найкращі ідеї!',
            'submit_title': 'Поділіться своєю ідеєю',
            'placeholder': 'Опишіть вашу пропозицію детально (мінімум 10 символів)...',
            'submit_btn': 'Надіслати пропозицію',
            'sign_in_hint': 'Увійдіть на сайт, щоб надіслати пропозицію або проголосувати.',
            'empty': 'Пропозицій поки немає. Будьте першими!',
            'for_guide': 'Для статті:',
            'comments': 'коментар.',
            'add_comment_placeholder': 'Додати коментар...',
            'post': 'Надіслати',
        }
    },
    'bg': {
        'welcome': {
            'next_gen': 'Ново поколение DevSense',
            'browse_catalog': 'Към каталога',
            'try_quizzes': 'Реши тест',
            'guides': 'Ръководства',
            'technical_articles': 'Технически статии',
            'fresh_guides': 'Актуални статии за PHP, бази данни и архитектура.',
            'all_articles': 'Всички статии',
            'interview_quizzes': 'Интервю тестове',
            'quizzes_lead': 'Проверете знанията си и печелете точки.',
            'all_quizzes': 'Всички тестове',
            'ideas': 'Идеи',
            'community_board': 'Предложения',
            'suggestions_lead': 'Предлагайте теми за статии и гласувайте.',
            'votes': 'гласа',
            'view_suggestions': 'Всички предложения',
        },
        'suggestions': {
            'title': 'Предложения на общността | DevSense',
            'hero_title': 'Предложения на общността',
            'hero_lead': 'Предлагайте нови теми за статии и гласувайте за най-добрите идеи!',
            'submit_title': 'Споделете вашата идея',
            'placeholder': 'Опишете предложението си подробно...',
            'submit_btn': 'Изпрати идея',
            'sign_in_hint': 'Влезте, за да изпратите предложение или да гласувате.',
            'empty': 'Все още няма предложения.',
            'for_guide': 'За статия:',
            'comments': 'коментара',
            'add_comment_placeholder': 'Добави коментар...',
            'post': 'Публикувай',
        }
    },
    'de': {
        'welcome': {
            'next_gen': 'DevSense der nächsten Generation',
            'browse_catalog': 'Katalog durchsuchen',
            'try_quizzes': 'Quizzes testen',
            'guides': 'Anleitungen',
            'technical_articles': 'Technische Artikel',
            'fresh_guides': 'Aktuelle Anleitungen zu PHP, Datenbanken und Architektur.',
            'all_articles': 'Alle Artikel',
            'interview_quizzes': 'Interview-Quizzes',
            'quizzes_lead': 'Testen Sie Ihr Wissen und sammeln Sie XP.',
            'all_quizzes': 'Alle Quizzes',
            'ideas': 'Ideen',
            'community_board': 'Vorschläge',
            'suggestions_lead': 'Schlagen Sie Themen vor und stimmen Sie ab.',
            'votes': 'Stimmen',
            'view_suggestions': 'Alle Vorschläge',
        },
        'suggestions': {
            'title': 'Community-Vorschläge | DevSense',
            'hero_title': 'Community-Vorschläge',
            'hero_lead': 'Schlagen Sie neue Themen vor und stimmen Sie für die besten Ideen ab!',
            'submit_title': 'Idee einreichen',
            'placeholder': 'Beschreiben Sie Ihren Vorschlag im Detail...',
            'submit_btn': 'Idee absenden',
            'sign_in_hint': 'Melden Sie sich an, um Vorschläge einzureichen.',
            'empty': 'Noch keine Vorschläge vorhanden.',
            'for_guide': 'Für Anleitung:',
            'comments': 'Kommentare',
            'add_comment_placeholder': 'Kommentar hinzufügen...',
            'post': 'Senden',
        }
    },
    'fr': {
        'welcome': {
            'next_gen': 'DevSense nouvelle génération',
            'browse_catalog': 'Parcourir le catalogue',
            'try_quizzes': 'Essayer les quiz',
            'guides': 'Guides',
            'technical_articles': 'Articles techniques',
            'fresh_guides': 'Guides récents sur PHP, les bases de données et l\'architecture.',
            'all_articles': 'Tous les articles',
            'interview_quizzes': 'Quiz d\'entretien',
            'quizzes_lead': 'Testez vos compétences et gagnez des XP.',
            'all_quizzes': 'Tous les quiz',
            'ideas': 'Idées',
            'community_board': 'Suggestions',
            'suggestions_lead': 'Proposez des sujets et votez pour les idées.',
            'votes': 'votes',
            'view_suggestions': 'Voir les suggestions',
        },
        'suggestions': {
            'title': 'Suggestions de la communauté | DevSense',
            'hero_title': 'Suggestions de la communauté',
            'hero_lead': 'Proposez de nouveaux sujets et votez pour les meilleures idées !',
            'submit_title': 'Soumettre une idée',
            'placeholder': 'Décrivez votre suggestion en détail...',
            'submit_btn': 'Soumettre l\'idée',
            'sign_in_hint': 'Connectez-vous pour soumettre une suggestion.',
            'empty': 'Aucune suggestion pour le moment.',
            'for_guide': 'Pour le guide :',
            'comments': 'commentaires',
            'add_comment_placeholder': 'Ajouter un commentaire...',
            'post': 'Publier',
        }
    },
    'es': {
        'welcome': {
            'next_gen': 'Plataforma DevSense de próxima generación',
            'browse_catalog': 'Explorar catálogo',
            'try_quizzes': 'Probar cuestionarios',
            'guides': 'Guías',
            'technical_articles': 'Artículos técnicos',
            'fresh_guides': 'Guías frescas sobre PHP, bases de datos y arquitectura.',
            'all_articles': 'Todos los artículos',
            'interview_quizzes': 'Cuestionarios de entrevista',
            'quizzes_lead': 'Pon a prueba tus conocimientos y gana XP.',
            'all_quizzes': 'Todos los cuestionarios',
            'ideas': 'Ideas',
            'community_board': 'Sugerencias',
            'suggestions_lead': 'Propón temas y vota por las mejores ideas.',
            'votes': 'votos',
            'view_suggestions': 'Ver sugerencias',
        },
        'suggestions': {
            'title': 'Sugerencias de la comunidad | DevSense',
            'hero_title': 'Sugerencias de la comunidad',
            'hero_lead': '¡Propón nuevos temas y vota por las mejores ideas!',
            'submit_title': 'Enviar una sugerencia',
            'placeholder': 'Describe tu sugerencia en detalle...',
            'submit_btn': 'Enviar idea',
            'sign_in_hint': 'Inicia sesión para enviar una sugerencia.',
            'empty': 'Aún no hay sugerencias.',
            'for_guide': 'Para guía:',
            'comments': 'comentarios',
            'add_comment_placeholder': 'Añadir comentario...',
            'post': 'Publicar',
        }
    },
    'it': {
        'welcome': {
            'next_gen': 'Piattaforma DevSense di nuova generazione',
            'browse_catalog': 'Esplora catalogo',
            'try_quizzes': 'Prova i quiz',
            'guides': 'Guide',
            'technical_articles': 'Articoli tecnici',
            'fresh_guides': 'Guide aggiornate su PHP, database e architettura.',
            'all_articles': 'Tutti gli articoli',
            'interview_quizzes': 'Quiz per colloqui',
            'quizzes_lead': 'Metti alla prova le tue competenze e guadagna XP.',
            'all_quizzes': 'Tutti i quiz',
            'ideas': 'Idee',
            'community_board': 'Suggerimenti',
            'suggestions_lead': 'Proponi argomenti e vota le idee migliori.',
            'votes': 'voti',
            'view_suggestions': 'Vedi suggerimenti',
        },
        'suggestions': {
            'title': 'Suggerimenti della community | DevSense',
            'hero_title': 'Suggerimenti della community',
            'hero_lead': 'Proponi nuovi argomenti e vota le idee migliori!',
            'submit_title': 'Invia un suggerimento',
            'placeholder': 'Descrivi il tuo suggerimento nei dettagli...',
            'submit_btn': 'Invia idea',
            'sign_in_hint': 'Accedi per inviare un suggerimento.',
            'empty': 'Nessun suggerimento inviato finora.',
            'for_guide': 'Per guida:',
            'comments': 'commenti',
            'add_comment_placeholder': 'Aggiungi commento...',
            'post': 'Pubblica',
        }
    }
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, data in welcome_keys.items():
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
        else:
            last_bracket = content.rfind("];")
            if last_bracket != -1:
                section_str = f"    '{section_key}' => [\n"
                for k, v in section_dict.items():
                    escaped_v = v.replace("'", "\\'")
                    section_str += f"        '{k}' => '{escaped_v}',\n"
                section_str += "    ],\n"
                content = content[:last_bracket] + section_str + content[last_bracket:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
print("Populated welcome and suggestions keys across all languages!")

views_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\resources\views"

# Refactor welcome.blade.php
wel_file = os.path.join(views_dir, "welcome.blade.php")
if os.path.exists(wel_file):
    with open(wel_file, 'r', encoding='utf-8') as f:
        c = f.read()
    c = c.replace(":title=\"app()->getLocale() === 'ru' ? 'Главная' : 'Home'\"", ":title=\"__('ui.nav.home')\"")
    c = c.replace(":description=\"app()->getLocale() === 'ru' ? 'Полезные гайды по PHP и веб-разработке' : 'Useful guides for PHP and web development'\"", ":description=\"__('ui.welcome.hero_lead')\"")
    c = c.replace("🚀 {{ app()->getLocale() === 'ru' ? 'Новое поколение DevSense' : 'Next-gen DevSense Platform' }}", "🚀 {{ __('ui.welcome.next_gen') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Перейти в каталог' : 'Browse Catalog' }}", "{{ __('ui.welcome.browse_catalog') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Пройти квиз' : 'Try Quizzes' }}", "{{ __('ui.welcome.try_quizzes') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Гайды' : 'Guides' }}", "{{ __('ui.welcome.guides') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Технические статьи' : 'Technical Articles' }}", "{{ __('ui.welcome.technical_articles') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Актуальные статьи и руководства по PHP, базам данных и архитектуре высоконагруженных систем.' : 'Fresh guides on PHP runtime, databases, microservices, and high-load architecture.' }}", "{{ __('ui.welcome.fresh_guides') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Все статьи' : 'All Articles' }}", "{{ __('ui.welcome.all_articles') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Тесты' : 'Quizzes' }}", "{{ __('ui.welcome.try_quizzes') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Интервью Квизы' : 'Interview Quizzes' }}", "{{ __('ui.welcome.interview_quizzes') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Проверьте себя, зарабатывайте очки опыта (XP) и соревнуйтесь в таблице лидеров.' : 'Check your readiness for interview questions, gain XP, and unlock rare badges.' }}", "{{ __('ui.welcome.quizzes_lead') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Все квизы' : 'All Quizzes' }}", "{{ __('ui.welcome.all_quizzes') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Идеи' : 'Suggestions' }}", "{{ __('ui.welcome.ideas') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Предложения' : 'Community Board' }}", "{{ __('ui.welcome.community_board') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Предлагайте темы статей и функции платформы. Поддерживайте чужие идеи голосами.' : 'Suggest topics and vote on feature ideas submitted by other developers.' }}", "{{ __('ui.welcome.suggestions_lead') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'голосов' : 'votes' }}", "{{ __('ui.welcome.votes') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Все предложения' : 'View Suggestions' }}", "{{ __('ui.welcome.view_suggestions') }}")
    with open(wel_file, 'w', encoding='utf-8') as f:
        f.write(c)
    print("Refactored welcome.blade.php")

# Refactor suggestions/index.blade.php
sug_file = os.path.join(views_dir, "suggestions", "index.blade.php")
if os.path.exists(sug_file):
    with open(sug_file, 'r', encoding='utf-8') as f:
        c = f.read()
    c = c.replace("title=\"{{ app()->getLocale() === 'ru' ? 'Предложения сообщества | DevSense' : 'Community Suggestions | DevSense' }}\"", "title=\"{{ __('ui.suggestions.title') }}\"")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Предложения сообщества' : 'Community Suggestions' }}", "{{ __('ui.suggestions.hero_title') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Предлагайте новые темы для статей или улучшения функций платформы. Голосуйте за лучшие идеи!' : 'Suggest new tutorial topics or request platform enhancements. Upvote ideas you support!' }}", "{{ __('ui.suggestions.hero_lead') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Поделитесь своей идеей' : 'Submit a Suggestion' }}", "{{ __('ui.suggestions.submit_title') }}")
    c = c.replace("placeholder=\"{{ app()->getLocale() === 'ru' ? 'Опишите ваше предложение подробно (минимум 10 символов)...' : 'Describe your suggestion in detail (min 10 characters)...' }}\"", "placeholder=\"{{ __('ui.suggestions.placeholder') }}\"")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Отправить предложение' : 'Submit Idea' }}", "{{ __('ui.suggestions.submit_btn') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Войдите на сайт, чтобы отправить предложение или проголосовать.' : 'Sign in to submit a suggestion or cast your votes.' }}", "{{ __('ui.suggestions.sign_in_hint') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Предложений пока нет. Будьте первыми!' : 'No suggestions submitted yet. Be the first!' }}", "{{ __('ui.suggestions.empty') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Для статьи:' : 'For guide:' }}", "{{ __('ui.suggestions.for_guide') }}")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'коммент.' : 'comments' }}", "{{ __('ui.suggestions.comments') }}")
    c = c.replace("placeholder=\"{{ app()->getLocale() === 'ru' ? 'Добавить комментарий...' : 'Add comment...' }}\"", "placeholder=\"{{ __('ui.suggestions.add_comment_placeholder') }}\"")
    c = c.replace("{{ app()->getLocale() === 'ru' ? 'Отправить' : 'Post' }}", "{{ __('ui.suggestions.post') }}")
    with open(sug_file, 'w', encoding='utf-8') as f:
        f.write(c)
    print("Refactored suggestions/index.blade.php")
