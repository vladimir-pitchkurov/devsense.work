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
        // 1. Seed Badges
        $badgesData = [
            [
                'slug' => 'php-novice',
                'points_required' => 50,
                'image_path' => '/images/badges/php-novice.svg',
                'translations' => [
                    'en' => [
                        'title' => 'PHP Novice',
                        'description' => 'Scored 50+ total points in quizzes.'
                    ],
                    'ru' => [
                        'title' => 'PHP Новичок',
                        'description' => 'Набрано более 50 очков в квизах.'
                    ]
                ]
            ],
            [
                'slug' => 'laravel-defender',
                'points_required' => 100,
                'image_path' => '/images/badges/laravel-defender.svg',
                'translations' => [
                    'en' => [
                        'title' => 'Laravel Defender',
                        'description' => 'Scored 100+ total points in quizzes.'
                    ],
                    'ru' => [
                        'title' => 'Защитник Laravel',
                        'description' => 'Набрано более 100 очков в квизах.'
                    ]
                ]
            ],
            [
                'slug' => 'tech-lead',
                'points_required' => 200,
                'image_path' => '/images/badges/tech-lead.svg',
                'translations' => [
                    'en' => [
                        'title' => 'Technical Lead',
                        'description' => 'Scored 200+ total points in quizzes.'
                    ],
                    'ru' => [
                        'title' => 'Технический Лид',
                        'description' => 'Набрано более 200 очков в квизах.'
                    ]
                ]
            ],
            [
                'slug' => 'writer-novice',
                'articles_required' => 1,
                'image_path' => '/images/badges/writer-novice.svg',
                'translations' => [
                    'en' => [
                        'title' => 'Novice Writer',
                        'description' => 'Published your first article.'
                    ],
                    'ru' => [
                        'title' => 'Начинающий писатель',
                        'description' => 'Опубликована первая статья.'
                    ]
                ]
            ],
            [
                'slug' => 'writer-prolific',
                'articles_required' => 5,
                'image_path' => '/images/badges/writer-prolific.svg',
                'translations' => [
                    'en' => [
                        'title' => 'Prolific Writer',
                        'description' => 'Published 5 articles.'
                    ],
                    'ru' => [
                        'title' => 'Плодовитый писатель',
                        'description' => 'Опубликовано 5 статей.'
                    ]
                ]
            ],
            [
                'slug' => 'writer-master',
                'articles_required' => 10,
                'image_path' => '/images/badges/writer-master.svg',
                'translations' => [
                    'en' => [
                        'title' => 'Master Writer',
                        'description' => 'Published 10 articles.'
                    ],
                    'ru' => [
                        'title' => 'Мастер пера',
                        'description' => 'Опубликовано 10 статей.'
                    ]
                ]
            ]
        ];

        foreach ($badgesData as $data) {
            $badge = Badge::create([
                'slug' => $data['slug'],
                'points_required' => $data['points_required'] ?? null,
                'articles_required' => $data['articles_required'] ?? null,
                'image_path' => $data['image_path'],
            ]);

            foreach ($data['translations'] as $locale => $tData) {
                $badge->translations()->create([
                    'locale' => $locale,
                    'title' => $tData['title'],
                    'description' => $tData['description'],
                ]);
            }
        }

        // 2. Seed Quiz: PHP 8.4
        $quiz1 = Quiz::create([
            'slug' => 'php-8-4-hooks',
            'points' => 50,
        ]);

        $quiz1->translations()->createMany([
            [
                'locale' => 'en',
                'title' => 'PHP 8.4 Properties & Hooks',
                'description' => 'Test your knowledge of the new property hooks feature introduced in PHP 8.4.',
            ],
            [
                'locale' => 'ru',
                'title' => 'Свойства и хуки PHP 8.4',
                'description' => 'Проверьте свои знания о новой функциональности хуков свойств в PHP 8.4.',
            ]
        ]);

        // Quiz 1 Questions
        $q1_1 = QuizQuestion::create([
            'quiz_id' => $quiz1->id,
            'type' => 'multiple_choice',
            'points' => 25,
            'correct_answer_index' => 1,
            'explanation' => 'Property hooks cannot be defined on readonly properties.',
        ]);
        $q1_1->translations()->create([
            'locale' => 'en',
            'question_text' => 'Do property hooks work with readonly properties?',
            'options' => ['Yes', 'No', 'Only if private'],
        ]);
        $q1_1->translations()->create([
            'locale' => 'ru',
            'question_text' => 'Работают ли хуки свойств с readonly свойствами?',
            'options' => ['Да', 'Нет', 'Только если они приватные'],
        ]);

        $q1_2 = QuizQuestion::create([
            'quiz_id' => $quiz1->id,
            'type' => 'multiple_choice',
            'points' => 25,
            'correct_answer_index' => 2,
            'explanation' => 'The variable $value is automatically provided to set hooks.',
        ]);
        $q1_2->translations()->create([
            'locale' => 'en',
            'question_text' => 'Which variable name represents the new value in a set hook?',
            'options' => ['$this', '$val', '$value'],
        ]);
        $q1_2->translations()->create([
            'locale' => 'ru',
            'question_text' => 'Какая переменная представляет новое значение в хуке set?',
            'options' => ['$this', '$val', '$value'],
        ]);

        // 3. Seed Quiz: Laravel Security
        $quiz2 = Quiz::create([
            'slug' => 'laravel-security',
            'points' => 50,
        ]);

        $quiz2->translations()->createMany([
            [
                'locale' => 'en',
                'title' => 'Laravel Security Best Practices',
                'description' => 'Test your understanding of securing Laravel web applications.',
            ],
            [
                'locale' => 'ru',
                'title' => 'Безопасность в Laravel',
                'description' => 'Проверьте понимание методов обеспечения безопасности веб-приложений на Laravel.',
            ]
        ]);

        // Quiz 2 Questions
        $q2_1 = QuizQuestion::create([
            'quiz_id' => $quiz2->id,
            'type' => 'multiple_choice',
            'points' => 25,
            'correct_answer_index' => 0,
            'explanation' => 'The @csrf directive renders a hidden input containing the CSRF token.',
        ]);
        $q2_1->translations()->create([
            'locale' => 'en',
            'question_text' => 'What directive is used in Blade templates to prevent Cross-Site Request Forgery?',
            'options' => ['@csrf', '@csrf_token', '@token'],
        ]);
        $q2_1->translations()->create([
            'locale' => 'ru',
            'question_text' => 'Какая директива используется в Blade-шаблонах для защиты от CSRF?',
            'options' => ['@csrf', '@csrf_token', '@token'],
        ]);

        $q2_2 = QuizQuestion::create([
            'quiz_id' => $quiz2->id,
            'type' => 'multiple_choice',
            'points' => 25,
            'correct_answer_index' => 1,
            'explanation' => 'whereRaw() does not sanitize raw inputs; you should use bindings instead.',
        ]);
        $q2_2->translations()->create([
            'locale' => 'en',
            'question_text' => 'Does the Eloquent Query Builder automatically sanitize inputs passed to whereRaw()?',
            'options' => ['Yes', 'No', 'Only on Postgres'],
        ]);
        $q2_2->translations()->create([
            'locale' => 'ru',
            'question_text' => 'Автоматически ли Eloquent Query Builder санитаризирует входные данные, переданные в whereRaw()?',
            'options' => ['Да', 'Нет', 'Только на Postgres'],
        ]);
    }
}
