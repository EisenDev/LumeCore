<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$assets = DB::table('vault_assets')->select('id', 'user_id', 'status', 'batch_id', 'created_at')->latest()->limit(5)->get();
foreach($assets as $a) {
    echo "ID: {$a->id} | User: {$a->user_id} | Status: {$a->status} | Batch: {$a->batch_id} | Created: {$a->created_at}\n";
}
echo "Current User ID (if any): " . Auth::id() . "\n";
