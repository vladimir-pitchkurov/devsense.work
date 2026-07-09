<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
foreach ($tables as $tObj) {
    $count = DB::table($tObj->table_name)->count();
    echo "Table: {$tObj->table_name} | Rows: {$count}\n";
}
