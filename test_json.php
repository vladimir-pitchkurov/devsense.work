<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Quiz;
use App\Models\User;
use App\Http\Controllers\PublicQuizController;

$quiz = Quiz::where('slug', 'php-basics-interview')->first();
$showResultsData = null;
$lastCompletedTimestamp = null;
$inProgressData = null;

$html = view('quizzes.show', compact('quiz', 'lastCompletedTimestamp', 'showResultsData', 'inProgressData'))->render();
// Find the quizData block
if (preg_match('/const quizData = \{[\s\S]*?\};/', $html, $matches)) {
    echo $matches[0];
} else {
    echo "quizData not found";
}
