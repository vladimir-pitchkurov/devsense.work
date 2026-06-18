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
                    'ua' => [
                        'title' => 'Основи SQL',
                        'description' => 'Освойте реляційні бази даних, синтаксис SQL, фільтрацію, сортування, групування, JOIN та базові операції CRUD з транзакціями.'
                    ],
                    'bg' => [
                        'title' => 'Основи на SQL',
                        'description' => 'Овладейте релационните бази данни, синтаксиса на SQL, филтрирането, сортирането, групирането, JOIN и основните CRUD операции с транзакции.'
                    ],
                    'de' => [
                        'title' => 'SQL-Grundlagen',
                        'description' => 'Meistern Sie relationale Datenbanken, SQL-Syntax, Filtern, Sortieren, Gruppieren, JOINs und grundlegende CRUD-Operationen mit Transaktionen.'
                    ],
                    'fr' => [
                        'title' => 'Les bases du SQL',
                        'description' => 'Maîtrisez les bases de données relationnelles, la syntaxe SQL, le filtrage, le tri, les regroupements, les JOIN et les opérations CRUD de base avec les transactions.'
                    ],
                    'es' => [
                        'title' => 'Conceptos básicos de SQL',
                        'description' => 'Domine las bases de datos relacionales, la sintaxis de SQL, el filtrado, la ordenación, las agrupaciones, los JOIN y las operaciones CRUD básicas con transacciones.'
                    ],
                    'it' => [
                        'title' => 'Fondamenti di SQL',
                        'description' => 'Padroneggia i database relazionali, la sintassi SQL, il filtraggio, l\'ordinamento, i raggruppamenti, le JOIN e le operazioni CRUD di base con le transazioni.'
                    ],
                ],
                'chapters' => [
                    [
                        'slug' => 'relational-databases-select',
                        'order' => 1,
                        'translations' => [
                            'en' => ['title' => 'Relational Databases & SELECT'],
                            'ru' => ['title' => 'Реляционные базы данных и SELECT'],
                            'ua' => ['title' => 'Реляційні бази даних та SELECT'],
                            'bg' => ['title' => 'Релационни бази данни и SELECT'],
                            'de' => ['title' => 'Relationale Datenbanken & SELECT'],
                            'fr' => ['title' => 'Bases de données relationnelles & SELECT'],
                            'es' => ['title' => 'Bases de datos relacionales y SELECT'],
                            'it' => ['title' => 'Database relazionali e SELECT']
                        ]
                    ],
                    [
                        'slug' => 'sorting-limits-nulls',
                        'order' => 2,
                        'translations' => [
                            'en' => ['title' => 'Sorting, Limits & NULLs'],
                            'ru' => ['title' => 'Сортировка, лимиты и NULL'],
                            'ua' => ['title' => 'Сортування, ліміти та NULL'],
                            'bg' => ['title' => 'Сортиране, лимити и NULL'],
                            'de' => ['title' => 'Sortieren, Limits & NULLs'],
                            'fr' => ['title' => 'Tri, limites & NULLs'],
                            'es' => ['title' => 'Ordenación, límites y NULL'],
                            'it' => ['title' => 'Ordinamento, limiti e NULL']
                        ]
                    ],
                    [
                        'slug' => 'aggregates-grouping',
                        'order' => 3,
                        'translations' => [
                            'en' => ['title' => 'Aggregate Functions & Grouping'],
                            'ru' => ['title' => 'Агрегатные функции и группировка'],
                            'ua' => ['title' => 'Агрегатні функції та групування'],
                            'bg' => ['title' => 'Агрегатни функции и групиране'],
                            'de' => ['title' => 'Aggregatfunktionen & Gruppierung'],
                            'fr' => ['title' => 'Fonctions d\'agrégation & regroupement'],
                            'es' => ['title' => 'Funciones de agregación y agrupación'],
                            'it' => ['title' => 'Funzioni di aggregazione e raggruppamento']
                        ]
                    ],
                    [
                        'slug' => 'joins',
                        'order' => 4,
                        'translations' => [
                            'en' => ['title' => 'The Power of JOINs'],
                            'ru' => ['title' => 'Сила объединений (JOIN)'],
                            'ua' => ['title' => 'Сила об\'єднань (JOIN)'],
                            'bg' => ['title' => 'Силата на обединенията (JOIN)'],
                            'de' => ['title' => 'Die Macht der JOINs'],
                            'fr' => ['title' => 'La puissance des JOINs'],
                            'es' => ['title' => 'El poder de los JOIN'],
                            'it' => ['title' => 'La potenza delle JOIN']
                        ]
                    ],
                    [
                        'slug' => 'crud-transactions',
                        'order' => 5,
                        'translations' => [
                            'en' => ['title' => 'CRUD Operations & Transactions'],
                            'ru' => ['title' => 'CRUD операции и транзакции'],
                            'ua' => ['title' => 'Операції CRUD та транзакції'],
                            'bg' => ['title' => 'CRUD операции и транзакции'],
                            'de' => ['title' => 'CRUD-Operationen & Transaktionen'],
                            'fr' => ['title' => 'Opérations CRUD & transactions'],
                            'es' => ['title' => 'Operaciones CRUD y transacciones'],
                            'it' => ['title' => 'Operazioni CRUD e transazioni']
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
                    'ua' => [
                        'title' => 'Просунутий SQL',
                        'description' => 'Глибоке занурення в CTE, віконні функції, складні JOIN, матеріалізовані представлення, збережені процедури та тригери.'
                    ],
                    'bg' => [
                        'title' => 'Разширен SQL',
                        'description' => 'Дълбоко потапяне в CTE, прозоречни функции, сложни JOIN, материализирани изгледи, съхранени процедури и тригери.'
                    ],
                    'de' => [
                        'title' => 'Fortgeschrittenes SQL',
                        'description' => 'Tiefes Eintauchen in CTEs, Fensterfunktionen, komplexe JOINs, materialisierte Sichten und gespeicherte Prozeduren oder Trigger.'
                    ],
                    'fr' => [
                        'title' => 'SQL Avancé',
                        'description' => 'Plongez dans les CTE, les fonctions de fenêtre (window functions), les JOIN complexes, les vues matérialisées, les procédures stockées et les déclencheurs (triggers).'
                    ],
                    'es' => [
                        'title' => 'SQL Avanzado',
                        'description' => 'Inmersión profunda en CTE, funciones de ventana, JOIN complejos, vistas materializadas, procedimientos almacenados y disparadores (triggers).'
                    ],
                    'it' => [
                        'title' => 'SQL Avanzato',
                        'description' => 'Approfondimento su CTE, funzioni finestra, JOIN complesse, viste materializzate, procedure stoccate e trigger.'
                    ],
                ],
                'chapters' => [
                    [
                        'slug' => 'cte-recursion',
                        'order' => 1,
                        'translations' => [
                            'en' => ['title' => 'Subqueries & Common Table Expressions (CTE)'],
                            'ru' => ['title' => 'Подзапросы и обобщенные табличные выражения (CTE)'],
                            'ua' => ['title' => 'Підзапити та узагальнені табличні вирази (CTE)'],
                            'bg' => ['title' => 'Подзаявки и обобщени таблични изрази (CTE)'],
                            'de' => ['title' => 'Unterabfragen & Common Table Expressions (CTE)'],
                            'fr' => ['title' => 'Sous-requêtes & expressions de table communes (CTE)'],
                            'es' => ['title' => 'Subconsultas y expresiones de tabla comunes (CTE)'],
                            'it' => ['title' => 'Sottoquery ed espressioni di tabella comuni (CTE)']
                        ]
                    ],
                    [
                        'slug' => 'window-functions',
                        'order' => 2,
                        'translations' => [
                            'en' => ['title' => 'Window Functions: Analytics on the Fly'],
                            'ru' => ['title' => 'Оконные функции: аналитика на лету'],
                            'ua' => ['title' => 'Віконні функції: аналітика на льоту'],
                            'bg' => ['title' => 'Прозоречни функции: анализи в движение'],
                            'de' => ['title' => 'Fensterfunktionen: Analysen im laufenden Betrieb'],
                            'fr' => ['title' => 'Fonctions de fenêtre : analyses à la volée'],
                            'es' => ['title' => 'Funciones de ventana: análisis sobre la marcha'],
                            'it' => ['title' => 'Funzioni finestra: analisi al volo']
                        ]
                    ],
                    [
                        'slug' => 'non-equi-joins-sets',
                        'order' => 3,
                        'translations' => [
                            'en' => ['title' => 'Non-Equi JOINs & Set Operations'],
                            'ru' => ['title' => 'Неэквивалентные объединения и операции над множествами'],
                            'ua' => ['title' => 'Нееквівалентні об\'єднання та операції над множинами'],
                            'bg' => ['title' => 'Нееквивалентни обединения и операции с множества'],
                            'de' => ['title' => 'Non-Equi JOINs & Mengenoperationen'],
                            'fr' => ['title' => 'JOINs non équivalents & opérations sur les ensembles'],
                            'es' => ['title' => 'JOIN no equivalentes y operaciones de conjuntos'],
                            'it' => ['title' => 'JOIN non equivalenti e operazioni sugli insiemi']
                        ]
                    ],
                    [
                        'slug' => 'views-materialized-views',
                        'order' => 4,
                        'translations' => [
                            'en' => ['title' => 'Views & Materialized Views'],
                            'ru' => ['title' => 'Представления и материализованные представления'],
                            'ua' => ['title' => 'Представлення та матеріалізовані представлення'],
                            'bg' => ['title' => 'Изгледи и материализирани изгледи'],
                            'de' => ['title' => 'Sichten & Materialisierte Sichten'],
                            'fr' => ['title' => 'Vues & vues matérialisées'],
                            'es' => ['title' => 'Vistas y vistas materializadas'],
                            'it' => ['title' => 'Viste e viste materializzate']
                        ]
                    ],
                    [
                        'slug' => 'stored-procedures-triggers',
                        'order' => 5,
                        'translations' => [
                            'en' => ['title' => 'Stored Procedures, Functions & Triggers'],
                            'ru' => ['title' => 'Хранимые процедуры, функции и триггеры'],
                            'ua' => ['title' => 'Збережені процедури, функції та тригери'],
                            'bg' => ['title' => 'Съхранени процедури, функции и тригери'],
                            'de' => ['title' => 'Gespeicherte Prozeduren, Funktionen & Trigger'],
                            'fr' => ['title' => 'Procédures stockées, fonctions & déclencheurs'],
                            'es' => ['title' => 'Procedimientos almacenados, funciones y disparadores'],
                            'it' => ['title' => 'Procedure stoccate, funzioni e trigger']
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
                    'ua' => [
                        'title' => 'Експертний SQL та оновлення',
                        'description' => 'Вивчіть внутрішній устрій БД: MVCC, блокування, плани виконання (EXPLAIN), індекси експертного рівня, секціонування та ефективну пагинацію.'
                    ],
                    'bg' => [
                        'title' => 'Експертен SQL и оптимизация',
                        'description' => 'Изучете вътрешното устройство на БД: MVCC, блокировки, планове на заявки (EXPLAIN), индекси на експертно ниво, секциониране и ефективно страниране.'
                    ],
                    'de' => [
                        'title' => 'Experten-SQL & Optimierung',
                        'description' => 'Entsperren Sie Datenbank-Interna: MVCC, Sperren, Ausführungspläne (EXPLAIN), Indexierung auf Expertenebene, Partitionierung und Paginierung großer Datenmengen.'
                    ],
                    'fr' => [
                        'title' => 'SQL Expert & Optimisation',
                        'description' => 'Découvrez les rouages internes des bases de données : MVCC, verrous (locking), plans d\'exécution (EXPLAIN), indexation de niveau expert, partitionnement et pagination à grand volume.'
                    ],
                    'es' => [
                        'title' => 'SQL Experto y Optimización',
                        'description' => 'Descubra el funcionamiento interno de las bases de datos: MVCC, bloqueos, planes de ejecución (EXPLAIN), indexación de nivel experto, particionado y paginación de gran volumen.'
                    ],
                    'it' => [
                        'title' => 'SQL Esperto e Ottimizzazione',
                        'description' => 'Scopri il funzionamento interno del database: MVCC, blocchi, piani di esecuzione (EXPLAIN), indicizzazione a livello esperto, partizionamento e paginazione ad alto volume.'
                    ],
                ],
                'chapters' => [
                    [
                        'slug' => 'concurrency-mvcc-bloat',
                        'order' => 1,
                        'translations' => [
                            'en' => ['title' => 'Concurrency, MVCC & Bloat'],
                            'ru' => ['title' => 'Параллелизм, MVCC и раздувание таблиц'],
                            'ua' => ['title' => 'Паралелізм, MVCC та роздування таблиць'],
                            'bg' => ['title' => 'Паралелизъм, MVCC и раздуване на таблици'],
                            'de' => ['title' => 'Nebenläufigkeit, MVCC & Bloat'],
                            'fr' => ['title' => 'Concurrence, MVCC & gonflement (bloat)'],
                            'es' => ['title' => 'Concurrencia, MVCC y fragmentación (bloat)'],
                            'it' => ['title' => 'Concorrenza, MVCC e bloat']
                        ]
                    ],
                    [
                        'slug' => 'dialects-json-search',
                        'order' => 2,
                        'translations' => [
                            'en' => ['title' => 'Dialects, Custom Types & JSON Search'],
                            'ru' => ['title' => 'Диалекты, кастомные типы и поиск по JSON'],
                            'ua' => ['title' => 'Діалекти, кастомні типи та пошук по JSON'],
                            'bg' => ['title' => 'Диалекти, персонализирани типове и търсене в JSON'],
                            'de' => ['title' => 'Dialekte, benutzerdefinierte Typen & JSON-Suche'],
                            'fr' => ['title' => 'Dialectes, types personnalisés & recherche JSON'],
                            'es' => ['title' => 'Dialectos, tipos personalizados y búsqueda JSON'],
                            'it' => ['title' => 'Dialetti, tipi personalizzati e ricerca JSON']
                        ]
                    ],
                    [
                        'slug' => 'execution-plans-scans',
                        'order' => 3,
                        'translations' => [
                            'en' => ['title' => 'Execution Plans (EXPLAIN ANALYZE) & Scan Types'],
                            'ru' => ['title' => 'Планы выполнения (EXPLAIN ANALYZE) и типы сканирования'],
                            'ua' => ['title' => 'Плани виконання (EXPLAIN ANALYZE) та типи сканування'],
                            'bg' => ['title' => 'Планове на изпълнение (EXPLAIN ANALYZE) и типове сканиране'],
                            'de' => ['title' => 'Ausführungspläne (EXPLAIN ANALYZE) & Scan-Typen'],
                            'fr' => ['title' => 'Plans d\'exécution (EXPLAIN ANALYZE) & types de balayage (scans)'],
                            'es' => ['title' => 'Planes de ejecución (EXPLAIN ANALYZE) y tipos de escaneo'],
                            'it' => ['title' => 'Piani di esecuzione (EXPLAIN ANALYZE) e tipi di scansione']
                        ]
                    ],
                    [
                        'slug' => 'indexes',
                        'order' => 4,
                        'translations' => [
                            'en' => ['title' => 'Indexes: Master Level'],
                            'ru' => ['title' => 'Индексы: мастер-класс'],
                            'ua' => ['title' => 'Індекси: майстер-клас'],
                            'bg' => ['title' => 'Индекси: експертно ниво'],
                            'de' => ['title' => 'Indizes: Expertenebene'],
                            'fr' => ['title' => 'Index : niveau expert'],
                            'es' => ['title' => 'Índices: nivel experto'],
                            'it' => ['title' => 'Indici: livello esperto']
                        ]
                    ],
                    [
                        'slug' => 'partitioning-pagination',
                        'order' => 5,
                        'translations' => [
                            'en' => ['title' => 'Partitioning & High-Volume Pagination'],
                            'ru' => ['title' => 'Секционирование и эффективная пагинация'],
                            'ua' => ['title' => 'Секціонування та ефективна пагінація'],
                            'bg' => ['title' => 'Секциониране и ефективно страниране'],
                            'de' => ['title' => 'Partitionierung & Paginierung großer Datenmengen'],
                            'fr' => ['title' => 'Partitionnement & pagination à grand volume'],
                            'es' => ['title' => 'Particionado y paginación de gran volumen'],
                            'it' => ['title' => 'Partizionamento e paginazione ad alto volume']
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

                if (file_exists($enQuizPath)) {
                    $quizQuestions['en'] = json_decode(file_get_contents($enQuizPath), true);
                    foreach ($locales as $locale) {
                        $localeQuizPath = "{$chapterDir}/{$locale}_quiz.json";
                        if (file_exists($localeQuizPath)) {
                            $quizQuestions[$locale] = json_decode(file_get_contents($localeQuizPath), true);
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
