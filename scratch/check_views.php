<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$compiler = app('blade.compiler');
$dir = new RecursiveDirectoryIterator(resource_path('views'));
$iterator = new RecursiveIteratorIterator($dir);

foreach ($iterator as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
        $path = $file->getPathname();
        $content = file_get_contents($path);
        try {
            $compiled = $compiler->compileString($content);
            // check syntax using eval inside isolated closure or php -l equivalent
            $tmp = tempnam(sys_get_temp_dir(), 'blade_');
            file_put_contents($tmp, $compiled);
            $out = [];
            $code = 0;
            exec("php -l " . escapeshellarg($tmp), $out, $code);
            unlink($tmp);
            if ($code !== 0) {
                echo "SYNTAX ERROR in view {$path}:\n" . implode("\n", $out) . "\n";
            }
        } catch (\Throwable $e) {
            echo "COMPILE ERROR in view {$path}: " . $e->getMessage() . "\n";
        }
    }
}
echo "Checked all views.\n";
