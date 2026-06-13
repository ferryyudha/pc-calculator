<?php

use App\Http\Controllers\Api\ComponentController;
use App\Http\Controllers\Api\CompatibilityController;
use App\Http\Controllers\Api\FpsController;
use App\Http\Controllers\Api\PsuController;
use App\Http\Controllers\Api\BuildController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\VerifyApiKey;
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
Route::post('/recommend-build',     [BuildController::class,         'recommend']); // §4.7

// Agent Endpoints
Route::post('/agent/register', [AgentController::class, 'register']);

Route::middleware([VerifyApiKey::class])->group(function () {
    Route::post('/agent/report', [AgentController::class, 'report']);
    Route::post('/agent/ping', [AgentController::class, 'ping']);
});

// Dashboard / Web Client Endpoints
Route::get('/download-agent', [DashboardController::class, 'downloadAgent']);
Route::get('/devices', [DashboardController::class, 'getDevices']);
Route::get('/devices/{id}', [DashboardController::class, 'getDeviceDetail']);
Route::get('/devices/{id}/logs', [DashboardController::class, 'getDeviceLogs']);
Route::get('/devices/{id}/power', [DashboardController::class, 'getPowerMetrics']);

Route::get('/alerts', [DashboardController::class, 'getAlerts']);
Route::patch('/alerts/{id}', [DashboardController::class, 'updateAlert']);

