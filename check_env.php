<?php
echo "REVERB_HOST from env(): " . env('REVERB_HOST') . "\n";
echo "REVERB_HOST from config(): " . config('broadcasting.connections.reverb.options.host') . "\n";
