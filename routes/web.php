<?php

use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Purchasing\PurchaseController;
use App\Http\Controllers\Suppliers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('auth')->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('products', ProductController::class)->except('show');
    Route::resource('suppliers', SupplierController::class)->except('show');

    Route::resource('purchases', PurchaseController::class);
    Route::post('purchases/{purchase}/post', [PurchaseController::class, 'post'])->name('purchases.post');

    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::post('stock', [StockController::class, 'store'])->name('stock.store');
});

require __DIR__.'/auth.php';
