<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$recommendService = app(App\Services\BuildRecommendationService::class);
$game = App\Models\Game::first();
if (!$game) {
    echo "No game found\n";
    exit;
}
echo "Using game: " . $game->name . "\n";

$result = $recommendService->recommend(10000000, $game, '1080p');
print_r($result);
