<?php

$langs = ['bg', 'de', 'es', 'fr', 'it', 'ru', 'ua'];

foreach ($langs as $lang) {
    $filepath = __DIR__ . "/../lang/{$lang}/ui.php";
    if (!file_exists($filepath)) {
        echo "Skipping {$lang} (not found)\n";
        continue;
    }

    $content = file_get_contents($filepath);

    // Let's split by 'suggestions' => [
    $parts = explode("'suggestions' => [", $content);
    if (count($parts) < 3) {
        echo "Error: expected at least 2 suggestions blocks in {$lang}, found " . (count($parts) - 1) . "\n";
        continue;
    }

    // Parse the first block
    $firstBlockRaw = "";
    $openBrackets = 1;
    $lines = explode("\n", $parts[1]);
    $idx = 0;
    while ($idx < count($lines) && $openBrackets > 0) {
        $line = $lines[$idx];
        $openBrackets += substr_count($line, '[');
        $openBrackets -= substr_count($line, ']');
        $firstBlockRaw .= $line . "\n";
        $idx++;
    }
    $betweenContent = implode("\n", array_slice($lines, $idx));

    // Parse the second block
    $secondBlockRaw = "";
    $openBrackets = 1;
    $linesSec = explode("\n", $parts[2]);
    $idxSec = 0;
    while ($idxSec < count($linesSec) && $openBrackets > 0) {
        $line = $linesSec[$idxSec];
        $openBrackets += substr_count($line, '[');
        $openBrackets -= substr_count($line, ']');
        $secondBlockRaw .= $line . "\n";
        $idxSec++;
    }
    $afterContent = implode("\n", array_slice($linesSec, $idxSec));

    // Function to parse simple key-values in a block
    $parseBlock = function($rawText) {
        $keys = [];
        $lines = explode("\n", $rawText);
        $i = 0;
        while ($i < count($lines)) {
            $line = trim($lines[$i]);
            if ($line === "") {
                $i++;
                continue;
            }
            if (preg_match("/^['\"]([a-zA-Z0-9_\-]+)['\"]\s*=>\s*(.*)$/", $line, $m)) {
                $key = $m[1];
                $valRest = $m[2];
                if (trim($valRest) === '[') {
                    $nestedRaw = "[\n";
                    $openB = 1;
                    $i++;
                    while ($i < count($lines) && $openB > 0) {
                        $nline = $lines[$i];
                        $openB += substr_count($nline, '[');
                        $openB -= substr_count($nline, ']');
                        $nestedRaw .= $nline . "\n";
                        $i++;
                    }
                    $keys[$key] = rtrim(trim($nestedRaw), ',');
                } else {
                    $keys[$key] = rtrim($valRest, ',');
                    $i++;
                }
            } else {
                $i++;
            }
        }
        return $keys;
    };

    $firstParsed = $parseBlock($firstBlockRaw);
    $secondParsed = $parseBlock($secondBlockRaw);

    // Merge keys
    $merged = $firstParsed;
    foreach ($secondParsed as $k => $v) {
        if ($k === 'title') {
            $merged['global_title'] = $v;
        } elseif ($k === 'placeholder') {
            $merged['global_placeholder'] = $v;
        } else {
            $merged[$k] = $v;
        }
    }

    // Reconstruct suggestions block
    ksort($merged);
    $newBlock = "    'suggestions' => [\n";
    foreach ($merged as $k => $v) {
        if (str_starts_with($v, '[')) {
            $nestedLines = explode("\n", $v);
            $newBlock .= "        '{$k}' => [\n";
            foreach (array_slice($nestedLines, 1) as $nline) {
                $newBlock .= "        {$nline}\n";
            }
            $newBlock = rtrim($newBlock, "\n") . ",\n";
        } else {
            $newBlock .= "        '{$k}' => {$v},\n";
        }
    }
    $newBlock .= "    ],";

    // Put everything together
    $newContent = $parts[0] . $newBlock . "\n" . trim($betweenContent) . "\n" . trim($afterContent);
    $newContent = preg_replace("/\n\s*\n\s*\n/", "\n\n", $newContent);

    file_put_contents($filepath, $newContent);
    echo "Successfully merged suggestions in {$lang}\n";
}
