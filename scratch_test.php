<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
$request = Request::create('/api/recommend-build/ai-prompt', 'POST', ['prompt' => 'rtx 5070 ti']);

$controller = app(App\Http\Controllers\Api\BuildController::class);
$response = $controller->recommendAiPrompt($request, app(App\Services\BuildPromptParserService::class));

echo $response->getContent() . "\n";
