<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$markdownService = app(App\Services\MarkdownContentService::class);
$data = $markdownService->getParsedContent('ru', 'security', 'web-app-security');
if ($data) {
    echo "Content found! HTML length: " . strlen($data['html']) . "\n";
    echo "Meta title: " . ($data['meta']['title'] ?? 'none') . "\n";
} else {
    echo "Content NOT found.\n";
}
