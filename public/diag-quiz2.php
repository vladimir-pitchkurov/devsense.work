<?php
// DIAGNOSTIC v2: check what translate() actually returns per locale
// ACCESS: yoursite.com/diag-quiz2.php (DELETE AFTER USE!)

define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Quiz;

header('Content-Type: text/plain; charset=utf-8');

echo "=== QUIZ TRANSLATE() TEST ===\n\n";

$locales = ['ru', 'en', 'ua', 'bg', 'de', 'fr', 'es', 'it'];

foreach ($locales as $locale) {
    // Simulate what the controller does: load with constraint
    $quiz = Quiz::with(['translations' => function ($q) use ($locale) {
        $q->where('locale', $locale);
    }, 'questions' => function ($q) {
        $q->limit(1);
    }, 'questions.translations' => function ($q) use ($locale) {
        $q->where('locale', $locale);
    }])->where('slug', 'php-basics-interview')->first();

    if (!$quiz) {
        echo "[$locale] Quiz NOT FOUND\n";
        continue;
    }

    // Simulate app locale
    app()->setLocale($locale);

    $trans = $quiz->translate();
    echo "[$locale] quiz->translate() = " . ($trans ? '"'.$trans->title.'"' : 'NULL') . "\n";
    echo "  loaded translations count: " . $quiz->translations->count() . "\n";
    echo "  first loaded locale: " . ($quiz->translations->first()?->locale ?? 'NONE') . "\n";

    $q = $quiz->questions->first();
    if ($q) {
        $qTrans = $q->translate();
        echo "  question->translate() = " . ($qTrans ? '"'.mb_substr($qTrans->question_text, 0, 60).'"' : 'NULL') . "\n";
        echo "  question loaded translations count: " . $q->translations->count() . "\n";
    }
    echo "\n";
}

echo "=== END ===\n";
