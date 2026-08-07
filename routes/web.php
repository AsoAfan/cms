<?php

use App\Http\Controllers\Catalog\AttributeController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('auth')->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('products', ProductController::class)->except('show');

    // Options are managed inline from their index screen, so no create or edit
    // pages of their own.
    Route::resource('attributes', AttributeController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/auth.php';
