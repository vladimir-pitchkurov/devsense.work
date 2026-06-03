<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            [
                'slug' => 'interactive-courses',
                'title_en' => 'Interactive Courses (Chapter-Quiz-XP)',
                'title_ru' => 'Интерактивные курсы (Глава-Квиз-XP)',
                'description_en' => 'Basic and advanced interactive developer courses structured as chapters with quizzes and experience points.',
                'description_ru' => 'Базовые и продвинутые интерактивные курсы для разработчиков в формате: глава - квиз - очки опыта.',
            ],
            [
                'slug' => 'browser-playground',
                'title_en' => 'In-Browser Coding Playground',
                'title_ru' => 'Песочница кода в браузере',
                'description_en' => 'Interactive code editors and challenges where you can write and run code directly in your browser.',
                'description_ru' => 'Интерактивные модули с написанием и запуском кода прямо в окне браузера.',
            ],
            [
                'slug' => 'user-articles',
                'title_en' => 'User Article Submissions',
                'title_ru' => 'Публикация пользовательских статей',
                'description_en' => 'Allow authors to submit articles on specific topics with review and community feedback.',
                'description_ru' => 'Возможность для авторов предлагать и публиковать статьи на определенные темы с прохождением ревью.',
            ],
            [
                'slug' => 'developer-feed',
                'title_en' => 'Developer Feed',
                'title_ru' => 'Лента активности разработчиков',
                'description_en' => 'A community feed showing developer accomplishments, comments, likes, and activity.',
                'description_ru' => 'Интерактивная лента активности сообщества, комментарии, обсуждения и достижения.',
            ],
            [
                'slug' => 'advanced-profiles',
                'title_en' => 'Advanced Developer Profiles',
                'title_ru' => 'Продвинутые профили соискателей',
                'description_en' => 'Rich developer portfolios showcasing experience points, badges, quizzes passed, and articles published.',
                'description_ru' => 'Детальные профили соискателей, демонстрирующие их XP, бейджи, пройденные тесты и статьи.',
            ],
            [
                'slug' => 'recruitment-module',
                'title_en' => 'HR & Recruitment Dashboard',
                'title_ru' => 'Модуль для HR и найма',
                'description_en' => 'A specialized dashboard for recruiters to discover developers and review applications.',
                'description_ru' => 'Специализированная панель для HR-специалистов для поиска разработчиков и анализа откликов.',
            ],
            [
                'slug' => 'job-board',
                'title_en' => 'Job Board & Vacancies',
                'title_ru' => 'Публикация вакансий',
                'description_en' => 'Allow companies to publish job vacancies and match with qualified developers.',
                'description_ru' => 'Раздел для публикации вакансий компаниями с возможностью отклика для разработчиков.',
            ],
            [
                'slug' => 'multi-language',
                'title_en' => 'More Languages Support',
                'title_ru' => 'Поддержка новых языков',
                'description_en' => 'Add translation and localization support for more languages to expand the reach.',
                'description_ru' => 'Добавление локализации и поддержки новых языков для расширения аудитории проекта.',
            ],
        ];

        foreach ($features as $feature) {
            Feature::updateOrCreate(['slug' => $feature['slug']], $feature);
        }
    }
}
