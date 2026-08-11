<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\CompetitorSelectionController;
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
    '/competitors/search',
    [CompetitorSelectionController::class, 'search']
)->name('competitors.search');

Route::post(
    '/competitors/add',
    [CompetitorSelectionController::class, 'add']
)->name('competitors.add');

Route::delete(
    '/competitors/remove',
    [CompetitorSelectionController::class, 'remove']
)->name('competitors.remove');

Route::get(
    '/analysis/email',
    [CompetitorSelectionController::class, 'email']
)->name('analysis.email');

Route::post(
    '/analysis/email',
    [CompetitorSelectionController::class, 'storeEmail']
)->name('analysis.email.store');

Route::get(
    '/preview',
    [PreviewController::class, 'competitors']
)->name('preview.competitors');
