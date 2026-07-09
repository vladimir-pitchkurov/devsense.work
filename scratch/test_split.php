<?php
$filepath = __DIR__ . "/../lang/ru/ui.php";
$content = file_get_contents($filepath);
$parts = explode("'suggestions' => [", $content);
echo "Number of parts: " . count($parts) . "\n";
foreach ($parts as $i => $p) {
    echo "Part #{$i} length: " . strlen($p) . "\n";
}
