<?php

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;

/*
|--------------------------------------------------------------------------
| Architecture
|--------------------------------------------------------------------------
|
| These encode the conventions this application is built to (P0.T5), so they
| are enforced rather than merely written down. Several cover namespaces that
| are still empty — they start guarding the moment the first class lands.
|
*/

arch('no debugging helpers reach the repository')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'print_r'])
    ->not->toBeUsed();

arch('controllers do not reach for the query builder')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Support\Facades\Schema']);

arch('form requests extend the framework base')
    ->expect('App\Http\Requests')
    ->toExtend(FormRequest::class);

arch('enums live in App\Enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('models live in App\Models')
    ->expect('App\Models')
    ->toExtend(Model::class);

arch('casts implement the cast contract')
    ->expect('App\Casts')
    ->toImplement(CastsAttributes::class)
    ->toBeFinal();

arch('support classes are final')
    ->expect('App\Support')
    ->toBeFinal();

arch('actions are final and expose a single entry point')
    ->expect('App\Actions')
    ->toBeFinal()
    ->toHaveMethod('handle');

arch('services are final')
    ->expect('App\Services')
    ->toBeFinal();

arch('read models are final and expose a single entry point')
    ->expect('App\Queries')
    ->toBeFinal()
    ->toHaveMethod('get');

arch('api resources extend the framework base')
    ->expect('App\Http\Resources')
    ->toExtend(JsonResource::class);

/*
|--------------------------------------------------------------------------
| Expenses are not purchases
|--------------------------------------------------------------------------
|
| Buying goods to resell increases inventory and only becomes a cost when the
| goods are sold. Rent is a cost the moment it is paid. Letting expenses reach
| the stock ledger would double-count the first and mistime the second.
|
*/

arch('expenses never touch inventory')
    ->expect([
        'App\Models\Expense',
        'App\Models\ExpenseCategory',
        'App\Http\Controllers\Expenses',
        'App\Http\Requests\Expenses',
    ])
    ->not->toUse([
        'App\Services\InventoryService',
        'App\Models\StockMovement',
        'App\Models\StockBatch',
        'App\Models\StockBatchConsumption',
        'App\Queries\StockOnHandQuery',
        'App\Queries\InventoryValuationQuery',
    ]);

arch('inventory never reaches for expenses')
    ->expect([
        'App\Services\InventoryService',
        'App\Queries\StockOnHandQuery',
        'App\Queries\InventoryValuationQuery',
        'App\Queries\InventoryReportQuery',
    ])
    ->not->toUse(['App\Models\Expense', 'App\Models\ExpenseCategory']);

/*
 * The same rule, one layer up: net profit = gross profit − expenses, so an
 * expense may only ever reach the accounts at that final subtraction. Every
 * query that derives cost of goods sold or a per-product margin has to be
 * blind to expenses, or rent starts being charged against the price of a
 * curtain. Only `ProfitReportQuery` is allowed to know about both.
 */
arch('expenses stay out of cost of goods sold')
    ->expect([
        'App\Queries\SalesReportQuery',
        'App\Queries\PurchaseReportQuery',
        'App\Queries\ProductProfitabilityQuery',
        'App\Queries\SupplierSummaryQuery',
    ])
    ->not->toUse([
        'App\Models\Expense',
        'App\Models\ExpenseCategory',
        'App\Queries\ExpenseReportQuery',
    ]);

/*
 * Buying stock is not a cost until the stock sells. Profit must never be
 * computed from what was spent on inventory, only from what left the shelf.
 */
arch('profit is never derived from purchases')
    ->expect('App\Queries\ProfitReportQuery')
    ->not->toUse([
        'App\Queries\PurchaseReportQuery',
        'App\Models\Purchase',
        'App\Models\PurchaseLine',
    ]);
