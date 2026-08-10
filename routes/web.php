<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::post('/language/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['id', 'en', 'ko'], true), 404);
    session(['locale' => $locale]);

    return back();
})->name('language.switch');

Route::get('/', [DashboardController::class, 'home'])->name('home');
Route::get('/riwayat', [DashboardController::class, 'history'])->name('history');
Route::get('/rekomendasi', [DashboardController::class, 'recommendations'])->name('recommendations');
Route::get('/chatbot', [DashboardController::class, 'chat'])->name('chat');
