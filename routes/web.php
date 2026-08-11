<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\GoogleBusinessController;
use App\Http\Controllers\PreviewController;
use Illuminate\Support\Facades\Route;

Route::get(
    '/',
    [AnalysisController::class, 'home']
)->name('home');

Route::get(
    '/google-business/search',
    [GoogleBusinessController::class, 'search']
)->name('google-business.search');

Route::post(
    '/analysis/start',
    [AnalysisController::class, 'start']
)->name('analysis.start');

Route::get(
    '/competitors',
    [AnalysisController::class, 'competitors']
)->name('competitors');

Route::get(
    '/preview',
    [PreviewController::class, 'competitors']
)->name('preview.competitors');