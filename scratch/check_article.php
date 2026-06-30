<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$a = App\Models\Article::where('slug', 'web-app-security')->first();
if ($a) {
    echo "pub: " . ($a->is_published ? 'yes' : 'no') . "\n";
    echo "app: " . ($a->is_approved ? 'yes' : 'no') . "\n";
    echo "author_approved: " . ($a->author->is_approved ? 'yes' : 'no') . "\n";
    echo "author_blocked: " . ($a->author->is_blocked ? 'yes' : 'no') . "\n";
} else {
    echo "Article not found.\n";
}
