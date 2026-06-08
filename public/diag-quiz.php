<?php
// DIAGNOSTIC: Quiz translations audit
// ACCESS: yoursite.com/diag-quiz.php (DELETE AFTER USE!)

define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

header('Content-Type: text/plain; charset=utf-8');

echo "=== QUIZ DIAGNOSTIC ===\n\n";

// 1. All quizzes in DB
echo "--- 1. ALL QUIZZES ---\n";
$quizzes = DB::table('quizzes')->get();
foreach ($quizzes as $q) {
    echo "  ID={$q->id}, slug={$q->slug}, points={$q->points}\n";
}

echo "\n--- 2. QUIZ_TRANSLATIONS per locale ---\n";
$qt = DB::table('quiz_translations')
    ->select('quiz_id', 'locale', DB::raw('COUNT(*) as cnt'), DB::raw('MIN(id) as min_id'), DB::raw('MAX(id) as max_id'))
    ->groupBy('quiz_id', 'locale')
    ->orderBy('quiz_id')
    ->orderBy('locale')
    ->get();
foreach ($qt as $row) {
    echo "  quiz_id={$row->quiz_id}, locale={$row->locale}, count={$row->cnt}, id_range=[{$row->min_id}..{$row->max_id}]\n";
}

echo "\n--- 3. QUIZ_QUESTIONS per quiz ---\n";
$qq = DB::table('quiz_questions')
    ->select('quiz_id', DB::raw('COUNT(*) as cnt'), DB::raw('MIN(id) as min_id'), DB::raw('MAX(id) as max_id'))
    ->groupBy('quiz_id')
    ->get();
foreach ($qq as $row) {
    echo "  quiz_id={$row->quiz_id}, question_count={$row->cnt}, id_range=[{$row->min_id}..{$row->max_id}]\n";
}

echo "\n--- 4. QUIZ_QUESTION_TRANSLATIONS per locale (for each quiz) ---\n";
$qtt = DB::table('quiz_question_translations as qt')
    ->join('quiz_questions as q', 'q.id', '=', 'qt.quiz_question_id')
    ->select('q.quiz_id', 'qt.locale', DB::raw('COUNT(*) as cnt'), DB::raw('MIN(qt.id) as min_id'), DB::raw('MAX(qt.id) as max_id'))
    ->groupBy('q.quiz_id', 'qt.locale')
    ->orderBy('q.quiz_id')
    ->orderBy('qt.locale')
    ->get();
foreach ($qtt as $row) {
    echo "  quiz_id={$row->quiz_id}, locale={$row->locale}, count={$row->cnt}, id_range=[{$row->min_id}..{$row->max_id}]\n";
}

echo "\n--- 5. SAMPLE: First quiz_translation for each locale (php-basics-interview) ---\n";
$quiz = DB::table('quizzes')->where('slug','php-basics-interview')->first();
if ($quiz) {
    $trans = DB::table('quiz_translations')->where('quiz_id', $quiz->id)->orderBy('locale')->get();
    foreach ($trans as $t) {
        echo "  locale={$t->locale}, id={$t->id}, title=" . substr($t->title, 0, 60) . "\n";
    }
} else {
    echo "  Quiz 'php-basics-interview' NOT FOUND!\n";
}

echo "\n--- 6. FIRST 3 QUESTIONS sample for quiz 'php-basics-interview' ---\n";
if ($quiz) {
    $questions = DB::table('quiz_questions')->where('quiz_id', $quiz->id)->orderBy('id')->limit(3)->get();
    foreach ($questions as $q) {
        echo "  question_id={$q->id}:\n";
        $trs = DB::table('quiz_question_translations')->where('quiz_question_id', $q->id)->orderBy('locale')->get();
        foreach ($trs as $t) {
            echo "    locale={$t->locale}, id={$t->id}, text=" . mb_substr($t->question_text, 0, 50) . "\n";
        }
    }
}

echo "\n--- 7. TOTAL quiz_question_translations in DB ---\n";
$total = DB::table('quiz_question_translations')->count();
echo "  Total rows: {$total}\n";
echo "  Expected: 100 questions x 8 locales = 800\n";

echo "\n=== END OF DIAGNOSTIC ===\n";
