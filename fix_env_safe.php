<?php
$env = file_get_contents('/var/www/lumecore/.env');

// Fix common REVERB variables and ensure no garbage
$replacements = [
    'REVERB_APP_ID' => '712656',
    'REVERB_APP_KEY' => 'ibjwkrs6rvg6c7zndxivit', // Restoring the NDXIVIT suffix if missing
    'REVERB_APP_SECRET' => 'unrvb7fndxivit',
    'REVERB_HOST' => '127.0.0.1',
    'REVERB_PORT' => '8080',
    'REVERB_SCHEME' => 'http',
    'VITE_REVERB_HOST' => 'lumecore.tech',
    'VITE_REVERB_PORT' => '443',
    'VITE_REVERB_SCHEME' => 'https',
    'VITE_REVERB_APP_KEY' => 'ibjwkrs6rvg6c7zndxivit',
];

$lines = explode("\n", str_replace("\r", "", $env));
$newLines = [];
$found = [];

foreach ($lines as $line) {
    if (empty(trim($line))) {
        $newLines[] = "";
        continue;
    }
    
    $isReverb = false;
    foreach ($replacements as $key => $value) {
        if (strpos($line, $key . '=') === 0) {
            $newLines[] = "{$key}={$value}";
            $found[$key] = true;
            $isReverb = true;
            break;
        }
    }
    
    if (!$isReverb) {
        // Keep non-reverb lines
        // Check for the "ch" garbage line and skip it
        if (strpos($line, 'ch"P_KEY}') !== false || strpos($line, 'ch"$_KEY}') !== false) {
             continue;
        }
        $newLines[] = $line;
    }
}

// Add missing
foreach ($replacements as $key => $value) {
    if (!isset($found[$key])) {
        $newLines[] = "{$key}={$value}";
    }
}

file_put_contents('/var/www/lumecore/.env', implode("\n", $newLines));
echo "DONE\n";
