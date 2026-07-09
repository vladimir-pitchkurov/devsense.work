<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- All Articles in DB ---\n";
foreach (App\Models\ArticleTranslation::all() as $at) {
    echo "Article Slug: {$at->article->slug} | Locale: {$at->locale} | Title: {$at->title}\n";
    if (str_contains($at->content, '<script>') || str_contains($at->content, 'iframe') || str_contains($at->content, 'onload')) {
        echo "WARNING: Suspicious content found in DB translation!\n";
        echo "Excerpt: " . substr($at->content, 0, 500) . "\n";
    }
}
