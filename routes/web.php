<?php

use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Customers\CustomerPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Expenses\ExpenseCategoryController;
use App\Http\Controllers\Expenses\ExpenseController;
use App\Http\Controllers\Purchasing\PurchaseController;
use App\Http\Controllers\Purchasing\QuickPurchaseController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\ReportExportController;
use App\Http\Controllers\Sales\QuickSaleController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Settings\BankController;
use App\Http\Controllers\Settings\CurrencyController;
use App\Http\Controllers\Settings\ExchangeRateController;
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

    // Customers keep a `show`, unlike suppliers: it is their statement — what
    // they bought, what they paid, and what is still on their loan. Payments are
    // recorded from that screen, which is the only place someone can see what is
    // owed on what, so they hang off the customer.
    Route::resource('customers', CustomerController::class)->whereNumber('customer');
    Route::post('customers/{customer}/payments', [CustomerPaymentController::class, 'store'])
        ->name('customers.payments.store')
        ->whereNumber('customer');
    Route::delete('customers/{customer}/payments/{payment}', [CustomerPaymentController::class, 'destroy'])
        ->name('customers.payments.destroy')
        ->whereNumber(['customer', 'payment']);

    // An invoice is written and edited in a drawer over the list, so there is
    // no create or edit page to route to. `status` moves it along — ordered,
    // on the way, arrived — and is what puts stock on the shelf.
    Route::resource('purchases', PurchaseController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy'])
        ->whereNumber('purchase');
    Route::post('purchases/{purchase}/status', [PurchaseController::class, 'status'])
        ->name('purchases.status')
        ->whereNumber('purchase');

    // Rung up and corrected in a drawer over the list, like a purchase, so
    // there is no create or edit page to route to. `status` moves it along —
    // ordered, on the way, received — and is what takes stock off the shelf.
    Route::resource('sales', SaleController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy'])
        ->whereNumber('sale');
    Route::post('sales/{sale}/status', [SaleController::class, 'status'])
        ->name('sales.status')
        ->whereNumber('sale');

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

    // Which currencies this business handles, and what each is worth in the one
    // the books are kept in. Rates are entered by hand and dated, so a document
    // converts at the rate that was in force on its own day.
    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('exchange-rates', [ExchangeRateController::class, 'index'])
            ->name('exchange-rates.index');
        Route::post('exchange-rates', [ExchangeRateController::class, 'store'])
            ->name('exchange-rates.store');
        Route::delete('exchange-rates/{exchangeRate}', [ExchangeRateController::class, 'destroy'])
            ->name('exchange-rates.destroy')
            ->whereNumber('exchangeRate');

        // The accounts non-cash money moves through. Set up once, then named on
        // every card or transfer — see `PaymentMethod::usesBank()`.
        Route::get('banks', [BankController::class, 'index'])->name('banks.index');
        Route::post('banks', [BankController::class, 'store'])->name('banks.store');
        Route::put('banks/{bank}', [BankController::class, 'update'])
            ->name('banks.update')
            ->whereNumber('bank');
        Route::delete('banks/{bank}', [BankController::class, 'destroy'])
            ->name('banks.destroy')
            ->whereNumber('bank');

        Route::post('currencies', [CurrencyController::class, 'store'])
            ->name('currencies.store');
        Route::post('currencies/{currency}/default', [CurrencyController::class, 'makeDefault'])
            ->name('currencies.default')
            ->whereNumber('currency');
        Route::delete('currencies/{currency}', [CurrencyController::class, 'destroy'])
            ->name('currencies.destroy')
            ->whereNumber('currency');
    });
});

require __DIR__.'/auth.php';
