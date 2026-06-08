<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$quiz = App\Models\Quiz::with('translations')->where('slug','php-basics-interview')->first();
if (!$quiz) {
    echo "Quiz not found!\n";
    exit(1);
}
echo "Quiz ID: ".$quiz->id."\n";
echo "Translations locales: ".json_encode($quiz->translations->pluck('locale')). "\n\n";

// Now test translate() for each locale
foreach (['ru', 'en', 'ua', 'bg', 'de', 'fr', 'es', 'it'] as $locale) {
    $trans = $quiz->translate($locale);
    echo "Locale [{$locale}]: ".($trans ? $trans->title : 'NULL')."\n";
}

// Also check question translations count per locale
$q = $quiz->questions()->first();
if ($q) {
    $qtrans = $q->translations()->get();
    echo "\nFirst question translations locales: ".json_encode($qtrans->pluck('locale'))."\n";
}
