<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\SensorReadingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/sensor-readings', [SensorReadingController::class, 'store'])
        ->middleware(['device.token', 'throttle:sensor-ingest']);
    Route::get('/sensor-readings/latest', [SensorReadingController::class, 'latest']);
    Route::get('/sensor-readings/history', [SensorReadingController::class, 'history']);

    Route::post('/chat', ChatController::class)->middleware('throttle:chat');
    Route::post('/recommendations/explain', RecommendationController::class)->middleware('throttle:chat');
});
