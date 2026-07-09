<?php
$content = file_get_contents(__DIR__ . '/content.md');

if (!$content) {
    echo "Failed to load content.md!\n";
    exit;
}

// Find all script tags in the page
preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $content, $scripts);
echo "--- Scripts Found in Page ---\n";
foreach ($scripts[0] as $i => $s) {
    echo "Script #{$i}:\n" . substr($s, 0, 500) . "\n-------------------\n";
}

// Find all iframe tags
preg_match_all('/<iframe\b[^>]*>(.*?)<\/iframe>/is', $content, $iframes);
echo "--- Iframes Found in Page ---\n";
foreach ($iframes[0] as $i => $iframe) {
    echo "Iframe #{$i}:\n" . substr($iframe, 0, 500) . "\n-------------------\n";
}

// Check other elements
preg_match_all('/<a\b[^>]*href=["\'](javascript:[^"\']*)["\']/is', $content, $links);
echo "--- Javascript Links Found ---\n";
foreach ($links[1] as $i => $link) {
    echo "Link #{$i}: {$link}\n";
}
