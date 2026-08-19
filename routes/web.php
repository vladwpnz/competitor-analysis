<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\CompetitorSelectionController;
use App\Http\Controllers\GoogleBusinessController;
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
    '/accounts',
    [AnalysisController::class, 'competitors']
)->name('accounts');

Route::get(
    '/accounts/search',
    [CompetitorSelectionController::class, 'search']
)->name('accounts.search');

Route::post(
    '/accounts/add',
    [CompetitorSelectionController::class, 'add']
)->name('accounts.add');

Route::delete(
    '/accounts/remove',
    [CompetitorSelectionController::class, 'remove']
)->name('accounts.remove');

Route::get(
    '/analysis/email',
    [CompetitorSelectionController::class, 'email']
)->name('analysis.email');

Route::post(
    '/analysis/email',
    [CompetitorSelectionController::class, 'storeEmail']
)->name('analysis.email.store');
