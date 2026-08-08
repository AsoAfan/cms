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
    ->toExtend(FormRequest::class)
    // Traits shared between requests, not requests themselves.
    ->ignoring('App\Http\Requests\Concerns');

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
    ])
    ->not->toUse(['App\Models\Expense', 'App\Models\ExpenseCategory']);

/*
 * The report is a cash view: outcome is what was paid out in the window, and
 * cost of goods sold has no place in it. Letting the FIFO batch allocations
 * into this query would mix the two views and double-count a purchase — once
 * when it was paid for, and again when the goods sold.
 */
arch('the cash report never derives cost of goods sold')
    ->expect('App\Queries\CashFlowQuery')
    ->not->toUse([
        'App\Models\StockBatch',
        'App\Models\StockBatchConsumption',
        'App\Models\StockMovement',
        'App\Queries\InventoryValuationQuery',
        'App\Queries\StockOnHandQuery',
    ]);

/*
|--------------------------------------------------------------------------
| Currency is converted once, on the way in
|--------------------------------------------------------------------------
|
| Every amount reaches the database in the base currency, converted in the Form
| Request and nowhere else. Everything past that point — the actions that write
| documents, the stock ledger, and every read model behind a report — deals in
| one currency and must stay blind to the fact that others exist.
|
| A query that converted would let one report disagree with another depending on
| which rate it happened to pick up, and a ledger that converted would make COGS
| depend on when it was asked for rather than what was bought.
|
*/

arch('reports and the ledger never convert currency')
    ->expect([
        'App\Queries',
        'App\Services\InventoryService',
        'App\Actions\Purchasing',
        'App\Actions\Sales',
    ])
    ->not->toUse([
        'App\Support\ExchangeRates',
        'App\Services\CurrencyService',
        'App\Models\ExchangeRate',
    ])
    // The two exceptions, and the reason each is one: a document records the
    // rate it was converted at, which needs the scale constant but not a
    // conversion.
    ->ignoring([
        'App\Actions\Purchasing\QuickPurchaseAction',
        'App\Actions\Sales\QuickSaleAction',
        'App\Actions\Sales\SaveSaleAction',
    ]);

arch('exchange rates are read from the table, never from the network')
    ->expect('App\Services\CurrencyService')
    ->not->toUse('Illuminate\Support\Facades\Http');
