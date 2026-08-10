<?php

use App\Http\Controllers\AnalysisController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AnalysisController::class, 'home'])->name('home');

Route::post('/analysis/start', [AnalysisController::class, 'start'])
    ->name('analysis.start');

Route::get('/competitors', [AnalysisController::class, 'competitors'])
    ->name('competitors');