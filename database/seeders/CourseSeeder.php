<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure SQL category exists
        $category = Category::firstOrCreate(['slug' => 'sql']);
        foreach (['en', 'ru', 'ua', 'bg', 'de', 'fr', 'es', 'it'] as $locale) {
            $category->translations()->firstOrCreate(
                ['locale' => $locale],
                ['name' => 'SQL']
            );
        }

        $locales = ['en', 'ru', 'ua', 'bg', 'de', 'fr', 'es', 'it'];

        $coursesData = [
            [
                'slug' => 'sql-basics',
                'points' => 50,
                'translations' => [
                    'en' => [
                        'title' => 'SQL Basics',
                        'description' => 'Master relational databases, SQL syntax, filtering, sorting, groupings, JOINs, and basic CRUD operations with transactions.'
                    ],
                    'ru' => [
                        'title' => 'Основы SQL',
                        'description' => 'Освойте реляционные базы данных, синтаксис SQL, фильтрацию, сортировку, группировки, JOIN и базовые CRUD операции с транзакциями.'
                    ],
                ],
                'chapters' => [
                    [
                        'slug' => 'relational-databases-select',
                        'order' => 1,
                        'translations' => [
                            'en' => ['title' => 'Relational Databases & SELECT'],
                            'ru' => ['title' => 'Реляционные базы данных и SELECT']
                        ]
                    ],
                    [
                        'slug' => 'sorting-limits-nulls',
                        'order' => 2,
                        'translations' => [
                            'en' => ['title' => 'Sorting, Limits & NULLs'],
                            'ru' => ['title' => 'Сортировка, лимиты и NULL']
                        ]
                    ],
                    [
                        'slug' => 'aggregates-grouping',
                        'order' => 3,
                        'translations' => [
                            'en' => ['title' => 'Aggregate Functions & Grouping'],
                            'ru' => ['title' => 'Агрегатные функции и группировка']
                        ]
                    ],
                    [
                        'slug' => 'joins',
                        'order' => 4,
                        'translations' => [
                            'en' => ['title' => 'The Power of JOINs'],
                            'ru' => ['title' => 'Сила объединений (JOIN)']
                        ]
                    ],
                    [
                        'slug' => 'crud-transactions',
                        'order' => 5,
                        'translations' => [
                            'en' => ['title' => 'CRUD Operations & Transactions'],
                            'ru' => ['title' => 'CRUD операции и транзакции']
                        ]
                    ],
                ]
            ],
            [
                'slug' => 'advanced-sql',
                'points' => 50,
                'translations' => [
                    'en' => [
                        'title' => 'Advanced SQL',
                        'description' => 'Deep dive into CTEs, window functions, complex JOINs, materialized views, and stored procedures or triggers.'
                    ],
                    'ru' => [
                        'title' => 'Продвинутый SQL',
                        'description' => 'Глубокое погружение в CTE, оконные функции, сложные JOIN, материализованные представления, хранимые процедуры и триггеры.'
                    ],
                ],
                'chapters' => [
                    [
                        'slug' => 'cte-recursion',
                        'order' => 1,
                        'translations' => [
                            'en' => ['title' => 'Subqueries & Common Table Expressions (CTE)'],
                            'ru' => ['title' => 'Подзапросы и обобщенные табличные выражения (CTE)']
                        ]
                    ],
                    [
                        'slug' => 'window-functions',
                        'order' => 2,
                        'translations' => [
                            'en' => ['title' => 'Window Functions: Analytics on the Fly'],
                            'ru' => ['title' => 'Оконные функции: аналитика на лету']
                        ]
                    ],
                    [
                        'slug' => 'non-equi-joins-sets',
                        'order' => 3,
                        'translations' => [
                            'en' => ['title' => 'Non-Equi JOINs & Set Operations'],
                            'ru' => ['title' => 'Неэквивалентные объединения и операции над множествами']
                        ]
                    ],
                    [
                        'slug' => 'views-materialized-views',
                        'order' => 4,
                        'translations' => [
                            'en' => ['title' => 'Views & Materialized Views'],
                            'ru' => ['title' => 'Представления и материализованные представления']
                        ]
                    ],
                    [
                        'slug' => 'stored-procedures-triggers',
                        'order' => 5,
                        'translations' => [
                            'en' => ['title' => 'Stored Procedures, Functions & Triggers'],
                            'ru' => ['title' => 'Хранимые процедуры, функции и триггеры']
                        ]
                    ],
                ]
            ],
            [
                'slug' => 'expert-sql',
                'points' => 50,
                'translations' => [
                    'en' => [
                        'title' => 'Expert SQL & Optimization',
                        'description' => 'Unlock database internals: MVCC, locking, execution plans (EXPLAIN), master-level indexing, partitioning, and high-volume pagination.'
                    ],
                    'ru' => [
                        'title' => 'Эксперт SQL и оптимизация',
                        'description' => 'Изучите внутреннее устройство БД: MVCC, блокировки, планы запросов (EXPLAIN), индексы экспертного уровня, секционирование и эффективную пагинацию.'
                    ],
                ],
                'chapters' => [
                    [
                        'slug' => 'concurrency-mvcc-bloat',
                        'order' => 1,
                        'translations' => [
                            'en' => ['title' => 'Concurrency, MVCC & Bloat'],
                            'ru' => ['title' => 'Параллелизм, MVCC и раздувание таблиц']
                        ]
                    ],
                    [
                        'slug' => 'dialects-json-search',
                        'order' => 2,
                        'translations' => [
                            'en' => ['title' => 'Dialects, Custom Types & JSON Search'],
                            'ru' => ['title' => 'Диалекты, кастомные типы и поиск по JSON']
                        ]
                    ],
                    [
                        'slug' => 'execution-plans-scans',
                        'order' => 3,
                        'translations' => [
                            'en' => ['title' => 'Execution Plans (EXPLAIN ANALYZE) & Scan Types'],
                            'ru' => ['title' => 'Планы выполнения (EXPLAIN ANALYZE) и типы сканирования']
                        ]
                    ],
                    [
                        'slug' => 'indexes',
                        'order' => 4,
                        'translations' => [
                            'en' => ['title' => 'Indexes: Master Level'],
                            'ru' => ['title' => 'Индексы: мастер-класс']
                        ]
                    ],
                    [
                        'slug' => 'partitioning-pagination',
                        'order' => 5,
                        'translations' => [
                            'en' => ['title' => 'Partitioning & High-Volume Pagination'],
                            'ru' => ['title' => 'Секционирование и эффективная пагинация']
                        ]
                    ],
                ]
            ]
        ];

        foreach ($coursesData as $cData) {
            // Create or update Course
            $course = Course::updateOrCreate(
                ['slug' => $cData['slug']],
                ['points' => $cData['points']]
            );

            // Seed Course translations
            foreach ($locales as $locale) {
                $cTrans = $cData['translations'][$locale] ?? $cData['translations']['en'];
                $course->translations()->updateOrCreate(
                    ['locale' => $locale],
                    [
                        'title' => $cTrans['title'],
                        'description' => $cTrans['description'],
                    ]
                );
            }

            // Seed Chapters
            foreach ($cData['chapters'] as $chData) {
                // Determine if custom content / quiz files exist
                $chapterSlug = $chData['slug'];
                $chapterDir = resource_path("courses/{$cData['slug']}/{$chapterSlug}");
                
                // 1. Create or Update associated Quiz
                $quizSlug = "course-{$cData['slug']}-{$chapterSlug}";
                $quiz = Quiz::updateOrCreate(
                    ['slug' => $quizSlug],
                    [
                        'points' => 40,
                        'category_id' => $category->id,
                    ]
                );

                // Quiz translations
                foreach ($locales as $locale) {
                    $chTrans = $chData['translations'][$locale] ?? $chData['translations']['en'];
                    $quizTitle = $locale === 'ru' ? ($chTrans['title'] . ' - Тест') : ($chTrans['title'] . ' Quiz');
                    $quizDesc = $locale === 'ru' 
                        ? ("Проверьте свои знания по теме: " . $chTrans['title']) 
                        : ("Test your knowledge on: " . $chTrans['title']);

                    $quiz->translations()->updateOrCreate(
                        ['locale' => $locale],
                        [
                            'title' => $quizTitle,
                            'description' => $quizDesc,
                        ]
                    );
                }

                // Retrieve existing questions to update them in place (idempotent seed)

                // Load questions
                $quizQuestions = [];
                $enQuizPath = "{$chapterDir}/en_quiz.json";
                $ruQuizPath = "{$chapterDir}/ru_quiz.json";

                if (file_exists($enQuizPath)) {
                    $quizQuestions['en'] = json_decode(file_get_contents($enQuizPath), true);
                    foreach ($locales as $locale) {
                        if ($locale === 'ru' && file_exists($ruQuizPath)) {
                            $quizQuestions['ru'] = json_decode(file_get_contents($ruQuizPath), true);
                        } else {
                            // Fallback to English
                            $quizQuestions[$locale] = $quizQuestions['en'];
                        }
                    }
                } else {
                    // Generate 4 dummy questions
                    $dummyQuestions = [
                        [
                            'question_text' => 'What is the primary purpose of SQL?',
                            'options' => [
                                'To manage and query relational database management systems.',
                                'To design client-side web application user interfaces.',
                                'To compile code into machine-executable binaries.',
                                'To send asynchronous HTTP requests over the network.'
                            ],
                            'correct_answer_index' => 0,
                            'explanation' => 'SQL (Structured Query Language) is specifically designed to manage and manipulate data held in a relational database management system.',
                            'points' => 10
                        ],
                        [
                            'question_text' => 'Which clause is used to filter records in SQL?',
                            'options' => [
                                'HAVING',
                                'WHERE',
                                'GROUP BY',
                                'ORDER BY'
                            ],
                            'correct_answer_index' => 1,
                            'explanation' => 'The WHERE clause is used to filter rows before any groupings are made.',
                            'points' => 10
                        ],
                        [
                            'question_text' => 'What does NULL represent in databases?',
                            'options' => [
                                'An empty string.',
                                'A value of zero.',
                                'A missing or unknown value.',
                                'A boolean false value.'
                            ],
                            'correct_answer_index' => 2,
                            'explanation' => 'NULL represents the absence of a value or an unknown value in a database cell.',
                            'points' => 10
                        ],
                        [
                            'question_text' => 'Which JOIN returns all rows from the left table and matched rows from the right table?',
                            'options' => [
                                'INNER JOIN',
                                'RIGHT JOIN',
                                'LEFT JOIN',
                                'FULL OUTER JOIN'
                            ],
                            'correct_answer_index' => 2,
                            'explanation' => 'A LEFT JOIN returns all rows from the left table, and the matched rows from the right table (returning NULL if no match exists).',
                            'points' => 10
                        ]
                    ];

                    $dummyQuestionsRu = [
                        [
                            'question_text' => 'Какова основная цель использования SQL?',
                            'options' => [
                                'Управление и выполнение запросов к реляционным базам данных.',
                                'Разработка пользовательских интерфейсов веб-приложений.',
                                'Компиляция кода в исполняемые машинные файлы.',
                                'Отправка асинхронных HTTP-запросов по сети.'
                            ],
                            'correct_answer_index' => 0,
                            'explanation' => 'SQL (Structured Query Language) специально разработан для управления и манипулирования данными в реляционных СУБД.',
                            'points' => 10
                        ],
                        [
                            'question_text' => 'Какое ключевое слово используется для фильтрации записей в SQL?',
                            'options' => [
                                'HAVING',
                                'WHERE',
                                'GROUP BY',
                                'ORDER BY'
                            ],
                            'correct_answer_index' => 1,
                            'explanation' => 'Конструкция WHERE используется для фильтрации строк до выполнения любых группировок.',
                            'points' => 10
                        ],
                        [
                            'question_text' => 'Что представляет собой значение NULL в базах данных?',
                            'options' => [
                                'Пустую строку.',
                                'Числовой ноль.',
                                'Отсутствующее или неизвестное значение.',
                                'Логическое значение false.'
                            ],
                            'correct_answer_index' => 2,
                            'explanation' => 'NULL обозначает отсутствие значения или неизвестное значение в ячейке базы данных.',
                            'points' => 10
                        ],
                        [
                            'question_text' => 'Какой тип JOIN возвращает все строки из левой таблицы и только совпадающие строки из правой?',
                            'options' => [
                                'INNER JOIN',
                                'RIGHT JOIN',
                                'LEFT JOIN',
                                'FULL OUTER JOIN'
                            ],
                            'correct_answer_index' => 2,
                            'explanation' => 'LEFT JOIN возвращает все записи левой таблицы и подходящие записи правой таблицы (заполняя NULL, если соответствие не найдено).',
                            'points' => 10
                        ]
                    ];

                    $quizQuestions['en'] = $dummyQuestions;
                    foreach ($locales as $locale) {
                        if ($locale === 'ru') {
                            $quizQuestions['ru'] = $dummyQuestionsRu;
                        } else {
                            $quizQuestions[$locale] = $dummyQuestions;
                        }
                    }
                }

                $existingQuestions = $quiz->questions()->orderBy('id')->get();
                $enQuestions = $quizQuestions['en'];
                
                foreach ($enQuestions as $index => $enQ) {
                    $questionData = [
                        'type' => 'multiple_choice',
                        'points' => $enQ['points'] ?? 10,
                        'correct_answer_index' => $enQ['correct_answer_index'],
                        'explanation' => $enQ['explanation'],
                    ];

                    if (isset($existingQuestions[$index])) {
                        $question = $existingQuestions[$index];
                        $question->update($questionData);
                    } else {
                        $question = $quiz->questions()->create($questionData);
                    }

                    foreach ($locales as $locale) {
                        $locQ = $quizQuestions[$locale][$index] ?? $enQ;
                        $question->translations()->updateOrCreate(
                            ['locale' => $locale],
                            [
                                'question_text' => $locQ['question_text'] ?? $enQ['question_text'],
                                'options' => $locQ['options'] ?? $enQ['options'],
                            ]
                        );
                    }
                }

                // Delete any extra questions remaining in the database
                if ($existingQuestions->count() > count($enQuestions)) {
                    for ($i = count($enQuestions); $i < $existingQuestions->count(); $i++) {
                        $existingQuestions[$i]->delete();
                    }
                }

                // 2. Create or Update Chapter
                $chapter = CourseChapter::updateOrCreate(
                    [
                        'course_id' => $course->id,
                        'slug' => $chapterSlug,
                    ],
                    [
                        'quiz_id' => $quiz->id,
                        'order' => $chData['order'],
                    ]
                );

                // Load translations/markdown contents
                foreach ($locales as $locale) {
                    $chTrans = $chData['translations'][$locale] ?? $chData['translations']['en'];
                    
                    // Search for markdown content file
                    $mdFile = "{$chapterDir}/{$locale}.md";
                    if (!file_exists($mdFile)) {
                        $mdFile = "{$chapterDir}/en.md";
                    }

                    if (file_exists($mdFile)) {
                        $contentMarkdown = file_get_contents($mdFile);
                    } else {
                        // Generate stub markdown content
                        if ($locale === 'ru') {
                            $contentMarkdown = "# {$chTrans['title']}\n\n### Раздел находится в разработке\n\nМы активно работаем над созданием теории и практических примеров для этой главы. Скоро здесь появится подробный разбор темы с практическими примерами из MySQL и PostgreSQL.\n\n### Что будет изучено:\n- Теоретические основы и практическая применимость\n- Отличия в реализации между популярными СУБД\n- Оптимизация и разбор планов запросов";
                        } else {
                            $contentMarkdown = "# {$chTrans['title']}\n\n### Section Under Construction\n\nWe are actively working on writing the theoretical guide and practical examples for this chapter. Stay tuned for database-specific breakdowns for both MySQL and PostgreSQL.\n\n### What we will cover:\n- Core theoretical concepts and practical use cases\n- Detailed implementation differences between major engines\n- Execution plan analysis and query optimization";
                        }
                    }

                    $chapter->translations()->updateOrCreate(
                        ['locale' => $locale],
                        [
                            'title' => $chTrans['title'],
                            'content_markdown' => $contentMarkdown,
                        ]
                    );
                }
            }
        }
    }
}
