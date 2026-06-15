<?php

use App\Http\Controllers\Api\ComponentController;
use App\Http\Controllers\Api\CompatibilityController;
use App\Http\Controllers\Api\FpsController;
use App\Http\Controllers\Api\PsuController;
use App\Http\Controllers\Api\BuildController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute API PC Calculator — Blueprint v2.0
|--------------------------------------------------------------------------
| Semua rute bersifat publik (tidak perlu autentikasi).
| Format response standar: { success, data, meta } atau { success, error }
|*/

// Endpoint GET untuk data komponen (§4.3)
Route::get('/cpus',          [ComponentController::class, 'cpus']);
Route::get('/gpus',          [ComponentController::class, 'gpus']);
Route::get('/motherboards',  [ComponentController::class, 'motherboards']);
Route::get('/rams',          [ComponentController::class, 'rams']);
Route::get('/ssds',          [ComponentController::class, 'ssds']);
Route::get('/hdds',          [ComponentController::class, 'hdds']);
Route::get('/psus',          [ComponentController::class, 'psus']);
Route::get('/games',         [ComponentController::class, 'games']);
// Endpoint untuk mengambil jumlah live dari database (CPU, GPU, Game, Benchmark) — digunakan di counter halaman utama
Route::get('/stats',         [ComponentController::class, 'stats']);

// Endpoint POST untuk fitur utama
Route::post('/check-compatibility', [CompatibilityController::class, 'check']);  // §4.4
Route::post('/fps-estimate',        [FpsController::class,           'estimate']); // §4.5
Route::post('/fps-estimate-all',    [FpsController::class,           'estimateAll']);
Route::post('/psu-calculate',       [PsuController::class,           'calculate']); // §4.6
Route::post('/recommend-build',             [BuildController::class,         'recommend']); // §4.7
Route::post('/recommend-build/ai-prompt',   [BuildController::class,         'recommendAiPrompt']);
Route::post('/recommend-build/tier/{tier}', [BuildController::class, 'recommendTier']);

use App\Http\Controllers\Api\ChatController;
Route::post('/chat', [ChatController::class, 'send']);

