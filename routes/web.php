<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BuildRecommendationController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/build-recommendation', [BuildRecommendationController::class, 'index'])
    ->name('build.index');

Route::post('/build-recommendation/ai-prompt', [BuildRecommendationController::class, 'parseAndRecommend'])
    ->name('build.ai-prompt');

Route::post('/build-recommendation/tier/{tier}', [BuildRecommendationController::class, 'recommendByTier'])
    ->name('build.tier');

Route::post('/build-recommendation/manual', [BuildRecommendationController::class, 'recommendManual'])
    ->name('build.manual');

use App\Http\Controllers\Api\ChatController;

Route::post('/chat', [ChatController::class, 'send'])
    ->middleware('throttle:20,1') // rate limit: 20 request per menit per IP
    ->name('chat.send');
