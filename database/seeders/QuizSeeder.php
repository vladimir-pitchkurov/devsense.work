<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clean up old quizzes to prevent duplicates
        Quiz::whereIn('slug', ['php-8-4-hooks', 'laravel-security', 'php-advanced-interview', 'php-expert-interview'])->delete();

        // 2. Seed Badges
        $badgesData = [
            [
                'slug' => 'php-novice',
                'points_required' => 50,
                'image_path' => '/images/badges/php-novice.svg',
                'translations' => [
                    'en' => ['title' => 'PHP Novice', 'description' => 'Scored 50+ total points in quizzes.'],
                    'ru' => ['title' => 'PHP Новичок', 'description' => 'Набрано более 50 очков в квизах.'],
                    'ua' => ['title' => 'PHP Новачок', 'description' => 'Набрано більше 50 очок у квізах.'],
                    'bg' => ['title' => 'PHP Новак', 'description' => 'Набрани 50+ общо точки в тестове.'],
                    'de' => ['title' => 'PHP-Anfänger', 'description' => 'Erreichte 50+ Gesamtpunkte in Quizzes.'],
                    'fr' => ['title' => 'Novice PHP', 'description' => 'Obtenu 50+ points au total dans les quiz.'],
                    'es' => ['title' => 'Novato de PHP', 'description' => 'Obtuvo más de 50 puntos en total en cuestionarios.'],
                    'it' => ['title' => 'Novizio PHP', 'description' => 'Ottenuto 50+ punti totali nei quiz.'],
                ]
            ],
            [
                'slug' => 'laravel-defender',
                'points_required' => 100,
                'image_path' => '/images/badges/laravel-defender.svg',
                'translations' => [
                    'en' => ['title' => 'Laravel Defender', 'description' => 'Scored 100+ total points in quizzes.'],
                    'ru' => ['title' => 'Защитник Laravel', 'description' => 'Набрано более 100 очков в квизах.'],
                    'ua' => ['title' => 'Захисник Laravel', 'description' => 'Набрано більше 100 очок у квізах.'],
                    'bg' => ['title' => 'Защитник на Laravel', 'description' => 'Набрани 100+ общо точки в тестове.'],
                    'de' => ['title' => 'Laravel-Verteidiger', 'description' => 'Erreichte 100+ Gesamtpunkte in Quizzes.'],
                    'fr' => ['title' => 'Défenseur Laravel', 'description' => 'Obtenu 100+ points au total dans les quiz.'],
                    'es' => ['title' => 'Defensor de Laravel', 'description' => 'Obtuvo más de 100 puntos en total en cuestionarios.'],
                    'it' => ['title' => 'Difensore Laravel', 'description' => 'Ottenuto 100+ punti totali nei quiz.'],
                ]
            ],
            [
                'slug' => 'tech-lead',
                'points_required' => 200,
                'image_path' => '/images/badges/tech-lead.svg',
                'translations' => [
                    'en' => ['title' => 'Technical Lead', 'description' => 'Scored 200+ total points in quizzes.'],
                    'ru' => ['title' => 'Технический Лид', 'description' => 'Набрано более 200 очков в квизах.'],
                    'ua' => ['title' => 'Технічний Лід', 'description' => 'Набрано більше 200 очок у квізах.'],
                    'bg' => ['title' => 'Технически лидер', 'description' => 'Набрани 200+ общо точки в тестове.'],
                    'de' => ['title' => 'Technical Lead', 'description' => 'Erreichte 200+ Gesamtpunkte in Quizzes.'],
                    'fr' => ['title' => 'Directeur Technique', 'description' => 'Obtenu 200+ points au total dans les quiz.'],
                    'es' => ['title' => 'Líder Técnico', 'description' => 'Obtuvo más de 200 puntos en total en cuestionarios.'],
                    'it' => ['title' => 'Leader Tecnico', 'description' => 'Ottenuto 200+ punti totali nei quiz.'],
                ]
            ],
            [
                'slug' => 'writer-novice',
                'articles_required' => 1,
                'image_path' => '/images/badges/writer-novice.svg',
                'translations' => [
                    'en' => ['title' => 'Novice Writer', 'description' => 'Published your first article.'],
                    'ru' => ['title' => 'Начинающий писатель', 'description' => 'Опубликована первая статья.'],
                    'ua' => ['title' => 'Письменник-початківець', 'description' => 'Опубліковано першу статтю.'],
                    'bg' => ['title' => 'Начинаещ писател', 'description' => 'Публикува първата си статия.'],
                    'de' => ['title' => 'Nachwuchsautor', 'description' => 'Ersten Artikel veröffentlicht.'],
                    'fr' => ['title' => 'Écrivain Novice', 'description' => 'Publié votre premier article.'],
                    'es' => ['title' => 'Escritor Novato', 'description' => 'Publicó su primer artículo.'],
                    'it' => ['title' => 'Scrittore Novello', 'description' => 'Pubblicato il tuo primo articolo.'],
                ]
            ],
            [
                'slug' => 'writer-prolific',
                'articles_required' => 5,
                'image_path' => '/images/badges/writer-prolific.svg',
                'translations' => [
                    'en' => ['title' => 'Prolific Writer', 'description' => 'Published 5 articles.'],
                    'ru' => ['title' => 'Плодовитый писатель', 'description' => 'Опубликовано 5 статей.'],
                    'ua' => ['title' => 'Продуктивний письменник', 'description' => 'Опубліковано 5 статей.'],
                    'bg' => ['title' => 'Продуктивен писател', 'description' => 'Публикува 5 статии.'],
                    'de' => ['title' => 'Produktiver Autor', 'description' => '5 Artikel veröffentlicht.'],
                    'fr' => ['title' => 'Écrivain Prolifique', 'description' => 'Publié 5 articles.'],
                    'es' => ['title' => 'Escritor Prolífico', 'description' => 'Publicó 5 artículos.'],
                    'it' => ['title' => 'Scrittore Prolifico', 'description' => 'Pubblicato 5 articoli.'],
                ]
            ],
            [
                'slug' => 'writer-master',
                'articles_required' => 10,
                'image_path' => '/images/badges/writer-master.svg',
                'translations' => [
                    'en' => ['title' => 'Master Writer', 'description' => 'Published 10 articles.'],
                    'ru' => ['title' => 'Мастер пера', 'description' => 'Опубликовано 10 статей.'],
                    'ua' => ['title' => 'Майстер пера', 'description' => 'Опубліковано 10 статей.'],
                    'bg' => ['title' => 'Майстор писател', 'description' => 'Публикува 10 статии.'],
                    'de' => ['title' => 'Meisterautor', 'description' => '10 Artikel veröffentlicht.'],
                    'fr' => ['title' => 'Maître Écrivain', 'description' => 'Publié 10 articles.'],
                    'es' => ['title' => 'Escritor Maestro', 'description' => 'Publicó 10 artículos.'],
                    'it' => ['title' => 'Scrittore Maestro', 'description' => 'Pubblicato 10 articoli.'],
                ]
            ],
            [
                'slug' => 'php-basics-bronze',
                'quiz_slug' => 'php-basics-interview',
                'min_percentage' => 50,
                'image_path' => '/images/badges/php-basics-bronze.svg',
                'translations' => [
                    'en' => ['title' => 'PHP Basics Bronze', 'description' => 'Scored 50% or more on the PHP Basics Interview Quiz.'],
                    'ru' => ['title' => 'Бронза: Основы PHP', 'description' => 'Набрано 50% или более правильных ответов в квизе по основам PHP.'],
                    'ua' => ['title' => 'Бронза: Основи PHP', 'description' => 'Набрано 50% або більше правильних відповідей у квізі з основ PHP.'],
                    'bg' => ['title' => 'Бронз: Основи на PHP', 'description' => 'Резултат от 50% или повече на теста за основи на PHP.'],
                    'de' => ['title' => 'PHP-Grundlagen Bronze', 'description' => 'Erreichte 50% oder mehr im PHP-Grundlagen-Interview-Quiz.'],
                    'fr' => ['title' => 'Bronze de base PHP', 'description' => 'Obtenu 50% ou plus au quiz d\'entretien sur les bases de PHP.'],
                    'es' => ['title' => 'Bronce en Fundamentos de PHP', 'description' => 'Obtuvo un 50% o más en el cuestionario de entrevista sobre fundamentos de PHP.'],
                    'it' => ['title' => 'Bronzo in Fondamenti di PHP', 'description' => 'Ottenuto il 50% o più nel quiz di intervista sui fondamenti di PHP.'],
                ]
            ],
            [
                'slug' => 'php-basics-silver',
                'quiz_slug' => 'php-basics-interview',
                'min_percentage' => 70,
                'image_path' => '/images/badges/php-basics-silver.svg',
                'translations' => [
                    'en' => ['title' => 'PHP Basics Silver', 'description' => 'Scored 70% or more on the PHP Basics Interview Quiz.'],
                    'ru' => ['title' => 'Серебро: Основы PHP', 'description' => 'Набрано 70% или более правильных ответов в квизе по основам PHP.'],
                    'ua' => ['title' => 'Срібло: Основи PHP', 'description' => 'Набрано 70% або більше правильних відповідей у квізі з основ PHP.'],
                    'bg' => ['title' => 'Сребро: Основи на PHP', 'description' => 'Резултат от 70% или повече на теста за основи на PHP.'],
                    'de' => ['title' => 'PHP-Grundlagen Silber', 'description' => 'Erreichte 70% oder mehr im PHP-Grundlagen-Interview-Quiz.'],
                    'fr' => ['title' => 'Argent de base PHP', 'description' => 'Obtenu 70% ou plus au quiz d\'entretien sur les bases de PHP.'],
                    'es' => ['title' => 'Plata en Fundamentos de PHP', 'description' => 'Obtuvo un 70% o más en el cuestionario de entrevista sobre fundamentos de PHP.'],
                    'it' => ['title' => 'Argento in Fondamenti di PHP', 'description' => 'Ottenuto il 70% o più nel quiz di intervista sui fondamenti di PHP.'],
                ]
            ],
            [
                'slug' => 'php-basics-gold',
                'quiz_slug' => 'php-basics-interview',
                'min_percentage' => 85,
                'image_path' => '/images/badges/php-basics-gold.svg',
                'translations' => [
                    'en' => ['title' => 'PHP Basics Gold', 'description' => 'Scored 85% or more on the PHP Basics Interview Quiz.'],
                    'ru' => ['title' => 'Золото: Основы PHP', 'description' => 'Набрано 85% или более правильных ответов в квизе по основам PHP.'],
                    'ua' => ['title' => 'Золото: Основи PHP', 'description' => 'Набрано 85% або більше правильних відповідей у квізі з основ PHP.'],
                    'bg' => ['title' => 'Злато: Основи на PHP', 'description' => 'Резултат от 85% или повече на теста за основи на PHP.'],
                    'de' => ['title' => 'PHP-Grundlagen Gold', 'description' => 'Erreichte 85% oder mehr im PHP-Grundlagen-Interview-Quiz.'],
                    'fr' => ['title' => 'Or de base PHP', 'description' => 'Obtenu 85% ou plus au quiz d\'entretien sur les bases de PHP.'],
                    'es' => ['title' => 'Oro en Fundamentos de PHP', 'description' => 'Obtuvo un 85% o más en el cuestionario de entrevista sobre fundamentos de PHP.'],
                    'it' => ['title' => 'Oro in Fondamenti di PHP', 'description' => 'Ottenuto l\'85% o più nel quiz di intervista sui fondamenti di PHP.'],
                ]
            ],
            [
                'slug' => 'php-basics-expert',
                'quiz_slug' => 'php-basics-interview',
                'min_percentage' => 100,
                'image_path' => '/images/badges/php-basics-expert.svg',
                'translations' => [
                    'en' => ['title' => 'PHP Basics Expert', 'description' => 'Scored 100% on the PHP Basics Interview Quiz.'],
                    'ru' => ['title' => 'Эксперт: Основы PHP', 'description' => 'Набрано 100% правильных ответов в квизе по основам PHP.'],
                    'ua' => ['title' => 'Експерт: Основи PHP', 'description' => 'Набрано 100% правильних відповідей у квізі з основ PHP.'],
                    'bg' => ['title' => 'Експерт: Основи на PHP', 'description' => 'Резултат от 100% на теста за основи на PHP.'],
                    'de' => ['title' => 'PHP-Grundlagen Experte', 'description' => 'Erreichte 100% im PHP-Grundlagen-Interview-Quiz.'],
                    'fr' => ['title' => 'Expert de base PHP', 'description' => 'Obtenu 100% au quiz d\'entretien sur les bases de PHP.'],
                    'es' => ['title' => 'Experto en Fundamentos de PHP', 'description' => 'Obtuvo un 100% en el cuestionario de entrevista sobre fundamentos de PHP.'],
                    'it' => ['title' => 'Esperto in Fondamenti di PHP', 'description' => 'Ottenuto il 100% nel quiz di intervista sui fondamenti di PHP.'],
                ]
            ],
            [
                'slug' => 'php-advanced-bronze',
                'quiz_slug' => 'php-advanced-interview',
                'min_percentage' => 50,
                'image_path' => '/images/badges/php-advanced-bronze.svg',
                'translations' => [
                    'en' => ['title' => 'PHP Advanced Bronze', 'description' => 'Scored 50% or more on the PHP Advanced Interview Quiz.'],
                    'ru' => ['title' => 'Бронза: Продвинутый PHP', 'description' => 'Набрано 50% или более правильных ответов в квизе по продвинутому PHP.'],
                ]
            ],
            [
                'slug' => 'php-advanced-silver',
                'quiz_slug' => 'php-advanced-interview',
                'min_percentage' => 70,
                'image_path' => '/images/badges/php-advanced-silver.svg',
                'translations' => [
                    'en' => ['title' => 'PHP Advanced Silver', 'description' => 'Scored 70% or more on the PHP Advanced Interview Quiz.'],
                    'ru' => ['title' => 'Серебро: Продвинутый PHP', 'description' => 'Набрано 70% или более правильных ответов в квизе по продвинутому PHP.'],
                ]
            ],
            [
                'slug' => 'php-advanced-gold',
                'quiz_slug' => 'php-advanced-interview',
                'min_percentage' => 85,
                'image_path' => '/images/badges/php-advanced-gold.svg',
                'translations' => [
                    'en' => ['title' => 'PHP Advanced Gold', 'description' => 'Scored 85% or more on the PHP Advanced Interview Quiz.'],
                    'ru' => ['title' => 'Золото: Продвинутый PHP', 'description' => 'Набрано 85% или более правильных ответов в квизе по продвинутому PHP.'],
                ]
            ],
            [
                'slug' => 'php-advanced-expert',
                'quiz_slug' => 'php-advanced-interview',
                'min_percentage' => 100,
                'image_path' => '/images/badges/php-advanced-expert.svg',
                'translations' => [
                    'en' => ['title' => 'PHP Advanced Expert', 'description' => 'Scored 100% on the PHP Advanced Interview Quiz.'],
                    'ru' => ['title' => 'Эксперт: Продвинутый PHP', 'description' => 'Набрано 100% правильных ответов в квизе по продвинутому PHP.'],
                ]
            ],
            [
                'slug' => 'php-expert-bronze',
                'quiz_slug' => 'php-expert-interview',
                'min_percentage' => 50,
                'image_path' => '/images/badges/php-expert-bronze.svg',
                'translations' => [
                    'en' => ['title' => 'PHP Expert Bronze', 'description' => 'Scored 50% or more on the PHP Expert Interview Quiz.'],
                    'ru' => ['title' => 'Бронза: Эксперт PHP', 'description' => 'Набрано 50% или более правильных ответов в экспертном квизе по PHP.'],
                ]
            ],
            [
                'slug' => 'php-expert-silver',
                'quiz_slug' => 'php-expert-interview',
                'min_percentage' => 70,
                'image_path' => '/images/badges/php-expert-silver.svg',
                'translations' => [
                    'en' => ['title' => 'PHP Expert Silver', 'description' => 'Scored 70% or more on the PHP Expert Interview Quiz.'],
                    'ru' => ['title' => 'Серебро: Эксперт PHP', 'description' => 'Набрано 70% или более правильных ответов в экспертном квизе по PHP.'],
                ]
            ],
            [
                'slug' => 'php-expert-gold',
                'quiz_slug' => 'php-expert-interview',
                'min_percentage' => 85,
                'image_path' => '/images/badges/php-expert-gold.svg',
                'translations' => [
                    'en' => ['title' => 'PHP Expert Gold', 'description' => 'Scored 85% or more on the PHP Expert Interview Quiz.'],
                    'ru' => ['title' => 'Золото: Эксперт PHP', 'description' => 'Набрано 85% или более правильных ответов в экспертном квизе по PHP.'],
                ]
            ],
            [
                'slug' => 'php-expert-master',
                'quiz_slug' => 'php-expert-interview',
                'min_percentage' => 100,
                'image_path' => '/images/badges/php-expert-master.svg',
                'translations' => [
                    'en' => ['title' => 'PHP Expert Master', 'description' => 'Scored 100% on the PHP Expert Interview Quiz.'],
                    'ru' => ['title' => 'Мастер: Эксперт PHP', 'description' => 'Набрано 100% правильных ответов в экспертном квизе по PHP.'],
                ]
            ],
            [
                'slug' => 'js-basics-bronze',
                'quiz_slug' => 'javascript-basics-interview',
                'min_percentage' => 50,
                'image_path' => '/images/badges/js-basics-bronze.svg',
                'translations' => [
                    'en' => ['title' => 'JS Basics Bronze', 'description' => 'Scored 50% or more on the JavaScript Basics Interview Quiz.'],
                    'ru' => ['title' => 'Бронза: Основы JS', 'description' => 'Набрано 50% или более правильных ответов в квизе по основам JavaScript.'],
                    'ua' => ['title' => 'Бронза: Основи JS', 'description' => 'Набрано 50% або більше правильних відповідей у квізі з основ JavaScript.'],
                    'bg' => ['title' => 'Бронз: Основи на JS', 'description' => 'Резултат от 50% или повече на теста за основи на JS.'],
                    'de' => ['title' => 'JS-Grundlagen Bronze', 'description' => 'Erreichte 50% oder mehr im JavaScript-Grundlagen-Interview-Quiz.'],
                    'fr' => ['title' => 'Bronze de base JS', 'description' => 'Obtenu 50% ou plus au quiz d\'entretien sur les bases de JS.'],
                    'es' => ['title' => 'Bronce en Fundamentos de JS', 'description' => 'Obtuvo un 50% o más en el cuestionario de entrevista sobre fundamentos de JS.'],
                    'it' => ['title' => 'Bronzo in Fondamenti di JS', 'description' => 'Ottenuto il 50% o più nel quiz di intervista sui fondamenti di JS.'],
                ]
            ],
            [
                'slug' => 'js-basics-silver',
                'quiz_slug' => 'javascript-basics-interview',
                'min_percentage' => 70,
                'image_path' => '/images/badges/js-basics-silver.svg',
                'translations' => [
                    'en' => ['title' => 'JS Basics Silver', 'description' => 'Scored 70% or more on the JavaScript Basics Interview Quiz.'],
                    'ru' => ['title' => 'Серебро: Основы JS', 'description' => 'Набрано 70% или более правильных ответов в квизе по основам JavaScript.'],
                    'ua' => ['title' => 'Срібло: Основи JS', 'description' => 'Набрано 70% або більше правильних відповідей у квізі з основ JavaScript.'],
                    'bg' => ['title' => 'Сребро: Основи на JS', 'description' => 'Резултат от 70% или повече на теста за основи на JS.'],
                    'de' => ['title' => 'JS-Grundlagen Silber', 'description' => 'Erreichte 70% oder mehr im JavaScript-Grundlagen-Interview-Quiz.'],
                    'fr' => ['title' => 'Argent de base JS', 'description' => 'Obtenu 70% ou plus au quiz d\'entretien sur les bases de JS.'],
                    'es' => ['title' => 'Plata en Fundamentos de JS', 'description' => 'Obtuvo un 70% o más en el cuestionario de entrevista sobre fundamentos de JS.'],
                    'it' => ['title' => 'Argento in Fondamenti di JS', 'description' => 'Ottenuto il 70% o più nel quiz di intervista sui fondamenti di JS.'],
                ]
            ],
            [
                'slug' => 'js-basics-gold',
                'quiz_slug' => 'javascript-basics-interview',
                'min_percentage' => 85,
                'image_path' => '/images/badges/js-basics-gold.svg',
                'translations' => [
                    'en' => ['title' => 'JS Basics Gold', 'description' => 'Scored 85% or more on the JavaScript Basics Interview Quiz.'],
                    'ru' => ['title' => 'Золото: Основы JS', 'description' => 'Набрано 85% или более правильных ответов в квизе по основам JavaScript.'],
                    'ua' => ['title' => 'Золото: Основы JS', 'description' => 'Набрано 85% або більше правильних відповідей у квізі з основ JavaScript.'],
                    'bg' => ['title' => 'Злато: Основи на JS', 'description' => 'Резултат от 85% или повече на теста за основи на JS.'],
                    'de' => ['title' => 'JS-Grundlagen Gold', 'description' => 'Erreichte 85% oder mehr im JavaScript-Grundlagen-Interview-Quiz.'],
                    'fr' => ['title' => 'Or de base JS', 'description' => 'Obtenu 85% ou plus au quiz d\'entretien sur les bases de JS.'],
                    'es' => ['title' => 'Oro en Fundamentos de JS', 'description' => 'Obtuvo un 85% o más en el cuestionario de entrevista sobre fundamentos de JS.'],
                    'it' => ['title' => 'Oro in Fondamenti di JS', 'description' => 'Ottenuto l\'85% o più nel quiz di intervista sui fondamenti di JS.'],
                ]
            ],
            [
                'slug' => 'js-basics-expert',
                'quiz_slug' => 'javascript-basics-interview',
                'min_percentage' => 100,
                'image_path' => '/images/badges/js-basics-expert.svg',
                'translations' => [
                    'en' => ['title' => 'JS Basics Expert', 'description' => 'Scored 100% on the JavaScript Basics Interview Quiz.'],
                    'ru' => ['title' => 'Эксперт: Основы JS', 'description' => 'Набрано 100% правильных ответов в квизе по основам JavaScript.'],
                    'ua' => ['title' => 'Експерт: Основи JS', 'description' => 'Набрано 100% правильних відповідей у квізі з основ JavaScript.'],
                    'bg' => ['title' => 'Експерт: Основи на JS', 'description' => 'Резултат от 100% на теста за основи на JS.'],
                    'de' => ['title' => 'JS-Grundlagen Experte', 'description' => 'Erreichte 100% im JavaScript-Grundlagen-Interview-Quiz.'],
                    'fr' => ['title' => 'Expert de base JS', 'description' => 'Obtenu 100% au quiz d\'entretien sur les bases de JS.'],
                    'es' => ['title' => 'Experto en Fundamentos de JS', 'description' => 'Obtuvo un 100% en el cuestionario de entrevista sobre fundamentos de JS.'],
                    'it' => ['title' => 'Esperto in Fondamenti di JS', 'description' => 'Ottenuto il 100% nel quiz di intervista sui fondamenti di JS.'],
                ]
            ],
            [
                'slug' => 'js-advanced-bronze',
                'quiz_slug' => 'javascript-advanced-interview',
                'min_percentage' => 50,
                'image_path' => '/images/badges/js-advanced-bronze.svg',
                'translations' => [
                    'en' => ['title' => 'JS Advanced Bronze', 'description' => 'Scored 50% or more on the JavaScript Advanced Interview Quiz.'],
                    'ru' => ['title' => 'Бронза: JS Advanced', 'description' => 'Набрано 50% или более правильных ответов в продвинутом квизе по JavaScript.'],
                    'ua' => ['title' => 'Бронза: JS Advanced', 'description' => 'Набрано 50% або більше правильних відповідей у просунутому квізі з JavaScript.'],
                    'bg' => ['title' => 'Бронз: JS Advanced', 'description' => 'Резултат от 50% или повече на теста за напреднали по JavaScript.'],
                    'de' => ['title' => 'JS-Fortgeschritten Bronze', 'description' => 'Erreichte 50% oder mehr im JavaScript-Fortgeschrittenen-Interview-Quiz.'],
                    'fr' => ['title' => 'Bronze JS Avancé', 'description' => 'Obtenu 50% ou plus au quiz d\'entretien sur JS avancé.'],
                    'es' => ['title' => 'Bronce en JS Avanzado', 'description' => 'Obtuvo un 50% o más en el cuestionario de entrevista de JavaScript avanzado.'],
                    'it' => ['title' => 'Bronzo in JS Avanzato', 'description' => 'Ottenuto il 50% o più nel quiz di intervista su JavaScript avanzato.'],
                ]
            ],
            [
                'slug' => 'js-advanced-silver',
                'quiz_slug' => 'javascript-advanced-interview',
                'min_percentage' => 70,
                'image_path' => '/images/badges/js-advanced-silver.svg',
                'translations' => [
                    'en' => ['title' => 'JS Advanced Silver', 'description' => 'Scored 70% or more on the JavaScript Advanced Interview Quiz.'],
                    'ru' => ['title' => 'Серебро: JS Advanced', 'description' => 'Набрано 70% или более правильных ответов в продвинутом квизе по JavaScript.'],
                    'ua' => ['title' => 'Срібло: JS Advanced', 'description' => 'Набрано 70% або більше правильних відповідей у просунутому квізі з JavaScript.'],
                    'bg' => ['title' => 'Сребро: JS Advanced', 'description' => 'Резултат от 70% или повече на теста за напреднали по JavaScript.'],
                    'de' => ['title' => 'JS-Fortgeschritten Silber', 'description' => 'Erreichte 70% oder mehr im JavaScript-Fortgeschrittenen-Interview-Quiz.'],
                    'fr' => ['title' => 'Argent JS Avancé', 'description' => 'Obtenu 70% ou plus au quiz d\'entretien sur JS avancé.'],
                    'es' => ['title' => 'Plata en JS Avanzado', 'description' => 'Obtuvo un 70% o más en el cuestionario de entrevista de JavaScript avanzado.'],
                    'it' => ['title' => 'Argento in JS Avanzato', 'description' => 'Ottenuto il 70% o più nel quiz di intervista su JavaScript avanzato.'],
                ]
            ],
            [
                'slug' => 'js-advanced-gold',
                'quiz_slug' => 'javascript-advanced-interview',
                'min_percentage' => 85,
                'image_path' => '/images/badges/js-advanced-gold.svg',
                'translations' => [
                    'en' => ['title' => 'JS Advanced Gold', 'description' => 'Scored 85% or more on the JavaScript Advanced Interview Quiz.'],
                    'ru' => ['title' => 'Золото: JS Advanced', 'description' => 'Набрано 85% или более правильных ответов в продвинутом квизе по JavaScript.'],
                    'ua' => ['title' => 'Золото: JS Advanced', 'description' => 'Набрано 85% або більше правильних відповідей у просунутому квізі з JavaScript.'],
                    'bg' => ['title' => 'Злато: JS Advanced', 'description' => 'Резултат от 85% или повече на теста за напреднали по JavaScript.'],
                    'de' => ['title' => 'JS-Fortgeschritten Gold', 'description' => 'Erreichte 85% oder mehr im JavaScript-Fortgeschrittenen-Interview-Quiz.'],
                    'fr' => ['title' => 'Or JS Avancé', 'description' => 'Obtenu 85% ou plus au quiz d\'entretien sur JS avancé.'],
                    'es' => ['title' => 'Oro en JS Avanzado', 'description' => 'Obtuvo un 85% o más en el cuestionario de entrevista de JavaScript avanzado.'],
                    'it' => ['title' => 'Oro in JS Avanzato', 'description' => 'Ottenuto l\'85% o più nel quiz di intervista su JavaScript avanzato.'],
                ]
            ],
            [
                'slug' => 'js-advanced-expert',
                'quiz_slug' => 'javascript-advanced-interview',
                'min_percentage' => 100,
                'image_path' => '/images/badges/js-advanced-expert.svg',
                'translations' => [
                    'en' => ['title' => 'JS Advanced Expert', 'description' => 'Scored 100% on the JavaScript Advanced Interview Quiz.'],
                    'ru' => ['title' => 'Эксперт: JS Advanced', 'description' => 'Набрано 100% правильных ответов в продвинутом квизе по JavaScript.'],
                    'ua' => ['title' => 'Експерт: JS Advanced', 'description' => 'Набрано 100% правильних відповідей у просунутому квізі з JavaScript.'],
                    'bg' => ['title' => 'Експерт: JS Advanced', 'description' => 'Резултат от 100% на теста за напреднали по JavaScript.'],
                    'de' => ['title' => 'JS-Fortgeschritten Experte', 'description' => 'Erreichte 100% im JavaScript-Fortgeschrittenen-Interview-Quiz.'],
                    'fr' => ['title' => 'Expert JS Avancé', 'description' => 'Obtenu 100% au quiz d\'entretien sur JS avancé.'],
                    'es' => ['title' => 'Experto en JS Avanzado', 'description' => 'Obtuvo un 100% en el cuestionario de entrevista de JavaScript avanzado.'],
                    'it' => ['title' => 'Esperto in JS Avanzato', 'description' => 'Ottenuto il 100% nel quiz di intervista su JavaScript advanced.'],
                ]
            ],
            [
                'slug' => 'git-bronze',
                'quiz_slug' => 'git-interview',
                'min_percentage' => 50,
                'image_path' => '/images/badges/git-bronze.svg',
                'translations' => [
                    'en' => ['title' => 'Git Bronze', 'description' => 'Scored 50% or more on the Git Interview Quiz.'],
                    'ru' => ['title' => 'Бронза: Git', 'description' => 'Набрано 50% или более правильных ответов в квизе по Git.'],
                    'ua' => ['title' => 'Бронза: Git', 'description' => 'Набрано 50% або більше правильних відповідей у квізі з Git.'],
                    'bg' => ['title' => 'Бронз: Git', 'description' => 'Резултат от 50% или больше на теста за Git.'],
                    'de' => ['title' => 'Git Bronze', 'description' => 'Erreichte 50% oder mehr im Git-Interview-Quiz.'],
                    'fr' => ['title' => 'Bronze Git', 'description' => 'Obtenu 50% ou plus au quiz d\'entretien sur Git.'],
                    'es' => ['title' => 'Bronce en Git', 'description' => 'Obtuvo un 50% o más en el cuestionario de entrevista de Git.'],
                    'it' => ['title' => 'Bronzo Git', 'description' => 'Ottenuto il 50% o più nel quiz di intervista su Git.'],
                ]
            ],
            [
                'slug' => 'git-silver',
                'quiz_slug' => 'git-interview',
                'min_percentage' => 70,
                'image_path' => '/images/badges/git-silver.svg',
                'translations' => [
                    'en' => ['title' => 'Git Silver', 'description' => 'Scored 70% or more on the Git Interview Quiz.'],
                    'ru' => ['title' => 'Серебро: Git', 'description' => 'Набрано 70% или более правильных ответов в квизе по Git.'],
                    'ua' => ['title' => 'Срібло: Git', 'description' => 'Набрано 70% або більше правильних відповідей у квізі з Git.'],
                    'bg' => ['title' => 'Сребро: Git', 'description' => 'Резултат от 70% или больше на теста за Git.'],
                    'de' => ['title' => 'Git Silber', 'description' => 'Erreichte 70% oder mehr im Git-Interview-Quiz.'],
                    'fr' => ['title' => 'Argent Git', 'description' => 'Obtenu 70% ou plus au quiz d\'entretien sur Git.'],
                    'es' => ['title' => 'Plata en Git', 'description' => 'Obtuvo un 70% o más en el cuestionario de entrevista de Git.'],
                    'it' => ['title' => 'Argento Git', 'description' => 'Ottenuto il 70% o più nel quiz di intervista su Git.'],
                ]
            ],
            [
                'slug' => 'git-gold',
                'quiz_slug' => 'git-interview',
                'min_percentage' => 85,
                'image_path' => '/images/badges/git-gold.svg',
                'translations' => [
                    'en' => ['title' => 'Git Gold', 'description' => 'Scored 85% or more on the Git Interview Quiz.'],
                    'ru' => ['title' => 'Золото: Git', 'description' => 'Набрано 85% или более правильных ответов в квизе по Git.'],
                    'ua' => ['title' => 'Золото: Git', 'description' => 'Набрано 85% або більше правильних відповідей у квізі з Git.'],
                    'bg' => ['title' => 'Злато: Git', 'description' => 'Резултат от 85% или больше на теста за Git.'],
                    'de' => ['title' => 'Git Gold', 'description' => 'Erreichte 85% oder mehr im Git-Interview-Quiz.'],
                    'fr' => ['title' => 'Or Git', 'description' => 'Obtenu 85% ou plus au quiz d\'entretien sur Git.'],
                    'es' => ['title' => 'Oro en Git', 'description' => 'Obtuvo un 85% o más en el cuestionario de entrevista de Git.'],
                    'it' => ['title' => 'Oro Git', 'description' => 'Ottenuto l\'85% o più nel quiz di intervista su Git.'],
                ]
            ],
            [
                'slug' => 'git-expert',
                'quiz_slug' => 'git-interview',
                'min_percentage' => 100,
                'image_path' => '/images/badges/git-expert.svg',
                'translations' => [
                    'en' => ['title' => 'Git Expert', 'description' => 'Scored 100% on the Git Interview Quiz.'],
                    'ru' => ['title' => 'Эксперт: Git', 'description' => 'Набрано 100% правильных ответов в квизе по Git.'],
                    'ua' => ['title' => 'Експерт: Git', 'description' => 'Набрано 100% правильних відповідей у квізі з Git.'],
                    'bg' => ['title' => 'Експерт: Git', 'description' => 'Резултат от 100% на теста за Git.'],
                    'de' => ['title' => 'Git Experte', 'description' => 'Erreichte 100% im Git-Interview-Quiz.'],
                    'fr' => ['title' => 'Expert Git', 'description' => 'Obtenu 100% au quiz d\'entretien sur Git.'],
                    'es' => ['title' => 'Experto en Git', 'description' => 'Obtuvo un 100% en el cuestionario de entrevista de Git.'],
                    'it' => ['title' => 'Esperto Git', 'description' => 'Ottenuto il 100% nel quiz di intervista su Git.'],
                ]
            ]
        ];

        foreach ($badgesData as $data) {
            $badge = Badge::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'points_required' => $data['points_required'] ?? null,
                    'articles_required' => $data['articles_required'] ?? null,
                    'image_path' => $data['image_path'],
                    'quiz_slug' => $data['quiz_slug'] ?? null,
                    'min_percentage' => $data['min_percentage'] ?? null,
                ]
            );

            foreach ($data['translations'] as $locale => $tData) {
                $badge->translations()->updateOrCreate(
                    ['locale' => $locale],
                    [
                        'title' => $tData['title'],
                        'description' => $tData['description'],
                    ]
                );
            }
        }

        // 3. Seed Quizzes
        $phpQuizTrans = [
            'en' => ['title' => 'PHP Basics Interview', 'description' => 'A comprehensive test of 100 questions covering core PHP concepts, scope, OOP, magic methods, and functional PHP.'],
            'ru' => ['title' => 'Собеседование по основам PHP', 'description' => 'Комплексный тест из 100 вопросов, охватывающий основные концепции PHP, области видимости, ООП, магические методы и функциональный PHP.'],
            'ua' => ['title' => 'Співбесіда з основ PHP', 'description' => 'Комплексний тест із 100 питань, що охоплює основні концепції PHP, області видимости, ООП, магічні методи та функціональний PHP.'],
            'bg' => ['title' => 'Интервю за основи на PHP', 'description' => 'Изчерпателен тест от 100 въпроса, обхващащ основни концепции на PHP, области на видимост, ООП, магически методи и функционален PHP.'],
            'de' => ['title' => 'PHP-Grundlagen Interview', 'description' => 'Ein umfassender Test mit 100 Fragen zu den Kernkonzepten von PHP, Gültigkeitsbereichen, OOP, magischen Methoden und funktionellem PHP.'],
            'fr' => ['title' => 'Entretien sur les bases de PHP', 'description' => 'Un test complet de 100 questions couvrant les concepts fondamentaux de PHP, la portée, la POO, les méthodes magiques et le PHP fonctionnel.'],
            'es' => ['title' => 'Entrevista de Fundamentos de PHP', 'description' => 'Un examen exhaustivo de 100 preguntas que abarca conceptos básicos de PHP, ámbitos, POO, métodos mágicos y PHP funcional.'],
            'it' => ['title' => 'Colloquio sui Fondamenti di PHP', 'description' => 'Un test completo di 100 domande che copre i concetti chiave di PHP, ambito, OOP, metodi magici e PHP funzionale.'],
        ];
        $this->seedQuiz('php-basics-interview', 1000, $phpQuizTrans, 'php-basics-interview', 'php');

        $jsQuizTrans = [
            'en' => ['title' => 'JavaScript Basics Interview', 'description' => 'A comprehensive test of 100 questions covering core JavaScript concepts, scopes, closures, prototypes, async programming, arrays, and modules.'],
            'ru' => ['title' => 'Собеседование по основам JavaScript', 'description' => 'Комплексный тест из 100 вопросов, охватывающий основные концепции JavaScript, области видимости, замыкания, прототипы, асинхронное программирование, массивы и модули.'],
            'ua' => ['title' => 'Співбесіда з основ JavaScript', 'description' => 'Комплексний тест із 100 питань, що охоплює основні концепции JavaScript, області видимости, замикання, прототипи, асинхронне програмування, масиви та модулі.'],
            'bg' => ['title' => 'Интервю за основи на JavaScript', 'description' => 'Изчерпателен тест от 100 въпроса, обхващащ основни концепции на JavaScript, области на видимост, затваряния, прототипи, асинхронно програмиране, масиви и модули.'],
            'de' => ['title' => 'JavaScript-Grundlagen Interview', 'description' => 'Ein umfassender Test mit 100 Fragen zu den Kernkonzepten von JavaScript, Gültigkeitsbereichen, Closures, Prototypen, asynchroner Programmierung, Arrays und Modulen.'],
            'fr' => ['title' => 'Entretien sur les bases de JavaScript', 'description' => 'Un test complet de 100 questions couvrant les concepts fondamentaux de JavaScript, les portées, les fermetures, les prototypes, la programmation asynchrone, les tableaux et les modules.'],
            'es' => ['title' => 'Entrevista de Fundamentos de JavaScript', 'description' => 'Un examen exhaustivo de 100 preguntas que abarca conceptos básicos de JavaScript, ámbitos, closures, prototipos, programación asíncrona, arrays y módulos.'],
            'it' => ['title' => 'Colloquio sui Fondamenti di JavaScript', 'description' => 'Un test completo di 100 domande che copre i concetti chiave di JavaScript, ambiti, closure, prototipi, programmazione asincrona, array e moduli.'],
        ];
        $this->seedQuiz('javascript-basics-interview', 1000, $jsQuizTrans, 'javascript-basics-interview', 'javascript');

        $jsAdvancedQuizTrans = [
            'en' => ['title' => 'JavaScript Advanced Interview', 'description' => 'A challenging test of 100 questions covering closures, prototype chain, async design, proxies, memory leaks, modern ES specs, and JS engine internals.'],
            'ru' => ['title' => 'Продвинутое собеседование по JavaScript', 'description' => 'Сложный тест из 100 вопросов, охватывающий замыкания, прототипы, асинхронность, прокси, утечки памяти, современные стандарты ES и устройство JS-движков.'],
            'ua' => ['title' => 'Просунута співбесіда з JavaScript', 'description' => 'Складний тест із 100 питань, що охоплює замикання, прототипи, асинхронність, проксі, витоки пам’яті, сучасні стандарти ES та пристрій JS-рушіїв.'],
            'bg' => ['title' => 'Интервю за напреднали по JavaScript', 'description' => 'Предизвикателен тест от 100 въпроса, обхващащ затваряния, прототипи, асинхронно програмиране, прокси, изтичане на памет, ES спецификации и вътрешности на JS двигателя.'],
            'de' => ['title' => 'JavaScript-Fortgeschrittenen Interview', 'description' => 'Ein anspruchsvoller Test mit 100 Fragen zu Closures, Prototypenkette, asynchronem Design, Proxies, Speicherlecks, modernen ES-Spezifikationen und JS-Engine-Interna.'],
            'fr' => ['title' => 'Entretien JS Avancé', 'description' => 'Un test exigeant de 100 questions couvrant les fermetures, la chaîne de prototypes, la programmation asynchrone, les proxies, les fuites de mémoire, les spécifications ES et les rouages des moteurs JS.'],
            'es' => ['title' => 'Entrevista de JavaScript Avanzado', 'description' => 'Un examen desafiante de 100 preguntas que abarca closures, cadena de prototipos, diseño asíncrono, proxies, fugas de memoria, especificaciones de ES e internos del motor JS.'],
            'it' => ['title' => 'Colloquio su JavaScript Avanzato', 'description' => 'Un test impegnativo di 100 domande che copre closure, catena di prototipi, programmazione asincrona, proxy, perdite di memoria, specifiche ES e interni dei motori JS.'],
        ];
        $this->seedQuiz('javascript-advanced-interview', 1000, $jsAdvancedQuizTrans, 'javascript-advanced-interview', 'javascript');

        $gitQuizTrans = [
            'en' => ['title' => 'Git Interview Prep', 'description' => 'A comprehensive preparation test of 40 questions covering Git commands, branching models, merging, rebasing, recovering commits, and monorepos.'],
            'ru' => ['title' => 'Подготовка к собеседованию по Git', 'description' => 'Комплексный тест из 40 вопросов для подготовки к собеседованию: команды, ветвление, слияние и ребейз, восстановление коммитов и монорепозитории.'],
            'ua' => ['title' => 'Підготовка до співбесіди з Git', 'description' => 'Комплексний тест із 40 питань для підготовки до співбесіди: команди, розгалуження, злиття та ребейз, відновлення коммітів та монорепозиторії.'],
            'bg' => ['title' => 'Подготовка за интервю за Git', 'description' => 'Изчерпателен тест от 40 въпроса за подготовка за интервю: команди, клониране, сливане и рибейз, възстановяване на комити и монорепозитории.'],
            'de' => ['title' => 'Git-Interview Vorbereitung', 'description' => 'Ein umfassender Test mit 40 Fragen zur Vorbereitung auf Git-Interviews: Befehle, Branching, Merging, Rebasing, Commit-Wiederherstellung und Monorepos.'],
            'fr' => ['title' => 'Préparation à l\'entretien Git', 'description' => 'Un test complet de 40 questions pour se préparer aux entretiens sur Git : commandes, branchement, fusion, rebasage, récupération de commits et monorepos.'],
            'es' => ['title' => 'Preparación de Entrevista de Git', 'description' => 'Un examen exhaustivo de 40 preguntas para preparar entrevistas de Git: comandos, ramificaciones, fusión, rebase, recuperación de commits y monorepos.'],
            'it' => ['title' => 'Preparazione al Colloquio su Git', 'description' => 'Un test completo di 40 domande per la preparazione ai colloqui su Git: comandi, branching, merging, rebasing, recupero di commit e monorepo.'],
        ];
        $this->seedQuiz('git-interview', 400, $gitQuizTrans, 'git-interview', 'git');

        $phpAdvancedQuizTrans = [
            'en' => ['title' => 'PHP Advanced Interview', 'description' => 'A challenging test of 100 questions covering modern PHP 8.x, OOP, SPL, patterns, Composer internals, and security.'],
            'ru' => ['title' => 'Продвинутое собеседование по PHP', 'description' => 'Сложный тест из 100 вопросов, охватывающий стандарты PHP 8.x, ООП, структуры SPL, паттерны проектирования, Composer и безопасность.'],
        ];
        $this->seedQuiz('php-advanced-interview', 1000, $phpAdvancedQuizTrans, 'php-advanced-interview', 'php');

        $phpExpertQuizTrans = [
            'en' => ['title' => 'PHP Expert Interview', 'description' => 'An extreme test of 100 questions covering Zend Engine internals, memory management, Swoole, JIT, FFI, streams, and exploits.'],
            'ru' => ['title' => 'Собеседование уровня Эксперт по PHP', 'description' => 'Экстремальный тест из 100 вопросов, охватывающий устройство Zend Engine, управление памятью, асинхронность, FFI, сетевые сокеты и уязвимости.'],
        ];
        $this->seedQuiz('php-expert-interview', 1000, $phpExpertQuizTrans, 'php-expert-interview', 'php');
    }

    private function seedQuiz(string $slug, int $points, array $translations, string $resourceSubdir, string $categorySlug): void
    {
        $category = \App\Models\Category::firstOrCreate(['slug' => $categorySlug]);
        $categoryName = $categorySlug === 'php' ? 'PHP' : ($categorySlug === 'javascript' ? 'JavaScript' : ucfirst($categorySlug));
        foreach (['en', 'ru', 'ua', 'bg', 'de', 'fr', 'es', 'it'] as $locale) {
            $category->translations()->firstOrCreate(
                ['locale' => $locale],
                ['name' => $categoryName]
            );
        }

        $quiz = Quiz::updateOrCreate(
            ['slug' => $slug],
            [
                'points' => $points,
                'category_id' => $category->id,
            ]
        );

        foreach ($translations as $locale => $tData) {
            $quiz->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'title' => $tData['title'],
                    'description' => $tData['description'],
                ]
            );
        }

        // Delete existing questions for this quiz to prevent duplicates/accumulation, then recreate
        $quiz->questions()->delete();

        // Load Quiz Questions from localized JSON files
        $locales = ['en', 'ru', 'ua', 'bg', 'de', 'fr', 'es', 'it'];
        $quizData = [];
        foreach ($locales as $locale) {
            $path = resource_path("quizzes/{$resourceSubdir}/{$locale}.json");
            if (file_exists($path)) {
                $quizData[$locale] = json_decode(file_get_contents($path), true);
            } else {
                $quizData[$locale] = [];
            }
        }

        $enQuestions = $quizData['en'] ?? [];
        foreach ($enQuestions as $index => $enQ) {
            $question = QuizQuestion::create([
                'quiz_id' => $quiz->id,
                'type' => 'multiple_choice',
                'points' => $enQ['points'] ?? 10,
                'correct_answer_index' => $enQ['correct_answer_index'],
                'explanation' => $enQ['explanation'],
            ]);

            foreach ($locales as $locale) {
                $locQ = $quizData[$locale][$index] ?? $enQ;
                $question->translations()->create([
                    'locale' => $locale,
                    'question_text' => $locQ['question_text'] ?? $enQ['question_text'],
                    'options' => $locQ['options'] ?? $enQ['options'],
                ]);
            }
        }

        // Trigger notification
        app(\App\Services\NotificationService::class)->notifyNewQuiz($quiz);
    }
}
