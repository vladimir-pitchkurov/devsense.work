<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");

foreach ($tables as $tObj) {
    $tableName = $tObj->table_name;
    // Get columns
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = :table AND table_schema='public'", ['table' => $tableName]);
    
    foreach ($columns as $cObj) {
        $colName = $cObj->column_name;
        $type = $cObj->data_type;
        
        // If it's a character or text type, query it
        if (str_contains($type, 'char') || str_contains($type, 'text')) {
            try {
                $results = DB::table($tableName)
                    ->where($colName, 'LIKE', '%<script%')
                    ->orWhere($colName, 'LIKE', '%iframe%')
                    ->orWhere($colName, 'LIKE', '%onload%')
                    ->orWhere($colName, 'LIKE', '%onerror%')
                    ->get();
                    
                if ($results->isNotEmpty()) {
                    echo "Table: {$tableName} | Column: {$colName} | Rows Count: " . $results->count() . "\n";
                    foreach ($results as $row) {
                        // Print ID and matching snippet
                        $id = $row->id ?? 'N/A';
                        $val = $row->$colName;
                        echo "  [ID: {$id}] Snippet: " . substr(strip_tags($val), 0, 200) . "\n";
                    }
                }
            } catch (\Exception $e) {
                // Ignore columns that might not work or don't have id
            }
        }
    }
}
