<?php

use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Expenses\ExpenseCategoryController;
use App\Http\Controllers\Expenses\ExpenseController;
use App\Http\Controllers\Purchasing\PurchaseController;
use App\Http\Controllers\Purchasing\QuickPurchaseController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\ReportExportController;
use App\Http\Controllers\Sales\QuickSaleController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Suppliers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('auth')->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // The catalogue is one screen: create, edit and delete all post back to it
    // from drawers, so there is no create or edit page to route to.
    // `whereNumber` keeps `products/{product}` from swallowing paths that are
    // not a product at all, so the retired `/products/create` reads as the 404
    // it is rather than a method mismatch.
    Route::resource('products', ProductController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->whereNumber('product');

    // Buy or sell a single product without leaving the catalogue. Both write a
    // real posted document — see the Quick*Action classes.
    Route::post('products/{product}/purchase', QuickPurchaseController::class)
        ->name('products.purchase')
        ->whereNumber('product');
    Route::post('products/{product}/sell', QuickSaleController::class)
        ->name('products.sell')
        ->whereNumber('product');

    Route::resource('suppliers', SupplierController::class)->except('show');

    Route::resource('purchases', PurchaseController::class);
    Route::post('purchases/{purchase}/post', [PurchaseController::class, 'post'])->name('purchases.post');

    Route::resource('sales', SaleController::class);
    Route::post('sales/{sale}/post', [SaleController::class, 'post'])->name('sales.post');

    Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('expense-categories', ExpenseCategoryController::class)
        ->only(['store', 'update', 'destroy'])
        ->parameters(['expense-categories' => 'category']);

    // Reporting is one screen. It reads its window from `?from=&to=` or
    // `?preset=`, so a URL is the whole of the state and can be bookmarked or
    // sent to someone.
    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/', ReportController::class)->name('index');
        Route::get('export', ReportExportController::class)->name('export');
    });
});

require __DIR__.'/auth.php';
