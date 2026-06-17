<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$assistant = app(App\Services\ChatAssistantService::class);

echo "=== TEST: FORMAT PENULISAN DAFTAR KOMPONEN ===\n";
$res = $assistant->chat('Rekomendasikan rakitan PC Ryzen 5 dengan budget 5 juta');
echo "User: Rekomendasikan rakitan PC Ryzen 5 dengan budget 5 juta\n";
echo "Assistant:\n" . ($res['reply'] ?? 'Error') . "\n\n";
