<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "Adding 'details' column to 'scan_activities' table...\n";

if (!Schema::hasColumn('scan_activities', 'details')) {
    Schema::table('scan_activities', function (Blueprint $table) {
        $table->text('details')->nullable();
    });
    echo "Column 'details' added successfully!\n";
} else {
    echo "Column 'details' already exists.\n";
}
