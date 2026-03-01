<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "--- JOBS --- \n";
echo "Jobs Count: " . DB::table('jobs')->count() . "\n";
foreach(DB::table('jobs')->get() as $job) {
    echo "ID: {$job->id}, Q: {$job->queue}, Res: {$job->reserved_at}\n";
}

echo "\n--- ASSETS --- \n";
$assets = DB::table('vault_assets')->orderBy('created_at', 'desc')->limit(5)->get();
foreach($assets as $a) {
    echo "ID: {$a->id}, Status: {$a->status}, Created: {$a->created_at}\n";
}

echo "\n--- FAILED JOBS --- \n";
$failed = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->limit(5)->get();
foreach($failed as $f) {
    $payload = json_decode($f->payload);
    $name = $payload->displayName ?? 'Unknown';
    echo "ID: {$f->id}, Job: {$name}, Failed: {$f->failed_at}\n";
    echo "  Error: " . substr($f->exception, 0, 150) . "...\n";
}

echo "\n--- REVERB CHECK --- \n";
$conn = @fsockopen('127.0.0.1', 8080);
echo "127.0.0.1:8080 is " . ($conn ? "OPEN" : "CLOSED") . "\n";
if($conn) fclose($conn);
