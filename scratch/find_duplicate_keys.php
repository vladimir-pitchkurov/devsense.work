<?php
$content = file_get_contents(__DIR__ . '/../lang/ru/ui.php');

// We can parse the file content line-by-line and keep track of key paths
$lines = explode("\n", $content);
$keys = [];
$stack = [];

foreach ($lines as $lineNum => $line) {
    // Match keys: 'key' => or "key" =>
    if (preg_match('/^\s*[\'"]([a-zA-Z0-9_\-]+)[\'"]\s*=>\s*\[\s*$/', $line, $m)) {
        $key = $m[1];
        $stack[] = $key;
        $path = implode('.', $stack);
        if (isset($keys[$path])) {
            echo "Duplicate array key: {$path} at line " . ($lineNum + 1) . " (previously seen at line {$keys[$path]})\n";
        } else {
            $keys[$path] = $lineNum + 1;
        }
    } elseif (preg_match('/^\s*\]\s*,?\s*$/', $line)) {
        array_pop($stack);
    }
}
