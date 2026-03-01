<?php
require '/var/www/lumecore/vendor/autoload.php';
$app = require_once '/var/www/lumecore/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$p = \App\Models\ProjectAsset::latest()->first();
if ($p) {
    echo "Dispatching for project: " . $p->id . "\n";
    \App\Jobs\PerformProjectScan::dispatch($p, null, null);
    echo "Done\n";
} else {
    echo "No project found\n";
}
