<?php
$content = file_get_contents(__DIR__ . '/../lang/ru/ui.php');
$tokens = token_get_all($content);

$stack = [];
$depth = 0;
$lastKey = null;
$keysAtDepth = [];

foreach ($tokens as $token) {
    if (is_array($token)) {
        if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $lastKey = trim($token[1], '\'"');
        }
    } else {
        if ($token === '[') {
            $depth++;
            $stack[$depth] = $lastKey;
            $keysAtDepth[$depth] = [];
        } elseif ($token === ']') {
            array_pop($stack);
            $depth--;
        } elseif ($token === '=>') {
            if ($lastKey !== null) {
                $path = '';
                for ($i = 1; $i < $depth; $i++) {
                    $path .= ($path === '' ? '' : '.') . $stack[$i];
                }
                $fullPath = ($path === '' ? '' : $path . '.') . $lastKey;
                
                if (isset($keysAtDepth[$depth][$lastKey])) {
                    echo "Duplicate key: {$fullPath} at depth {$depth}\n";
                }
                $keysAtDepth[$depth][$lastKey] = true;
            }
        }
    }
}
