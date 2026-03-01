<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Events\AuditProgressUpdated;
use App\Models\VaultAsset;

$asset = VaultAsset::latest()->first();
if (!$asset) {
    echo "No asset found.\n";
    exit;
}

echo "Broadcasting to User: {$asset->user_id} | Asset: {$asset->id}\n";
AuditProgressUpdated::dispatch($asset, 'TEST EVENT AT ' . date('H:i:s'), 50, 'processing', 'Test details');
echo "Done.\n";
