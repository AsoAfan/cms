<?php

use App\Actions\Purchasing\PostPurchaseAction;
use App\Actions\Sales\PostSaleAction;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-03-15 09:00:00');

    $this->actingAs(User::factory()->create());

    $this->product = Product::factory()->create([
        'name' => 'Blackout 117x137',
        'code' => 'BLK-117',
    ]);

    $supplier = Supplier::factory()->create(['name' => 'Northwind Textiles']);

    $purchase = Purchase::factory()->for($supplier)->create([
        'invoiced_on' => '2026-03-01',
        'status' => PurchaseStatus::Draft,
    ]);
    $purchase->lines()->create([
        'product_id' => $this->product->id,
        'quantity' => 10,
        'unit_cost' => Money::fromDecimal('20.00'),
        'discount' => Money::zero(),
    ]);
    app(PostPurchaseAction::class)->handle($purchase->refresh());

    $sale = Sale::factory()->create([
        'sold_on' => '2026-03-05',
        'status' => SaleStatus::Draft,
        'payment_method' => PaymentMethod::Cash,
    ]);
    $sale->lines()->create([
        'product_id' => $this->product->id,
        'quantity' => 4,
        'unit_price' => Money::fromDecimal('44.00'),
        'discount' => Money::zero(),
    ]);
    app(PostSaleAction::class)->handle($sale->refresh());

    Expense::factory()->create([
        'expense_category_id' => ExpenseCategory::factory()->create(['name' => 'Rent'])->id,
        'amount' => Money::fromDecimal('60.00'),
        'spent_on' => '2026-03-02',
        'payment_method' => PaymentMethod::Transfer,
    ]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/*
|--------------------------------------------------------------------------
| The screens
|--------------------------------------------------------------------------
|
| 176.00 taken, 80.00 of goods off the shelf, 96.00 gross, 60.00 of rent,
| 36.00 net. Six units left at 20.00 each.
|
*/

it('shows the summary for the requested period', function () {
    $this->get('/reports?from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->where('period.from', '2026-03-01')
            ->where('period.to', '2026-03-31')
            ->where('profit.revenue', 17600)
            ->where('profit.cost_of_goods_sold', 8000)
            ->where('profit.gross_profit', 9600)
            ->where('profit.expenses', 6000)
            ->where('profit.net_profit', 3600)
            ->where('inventory.total_value', 12000)
            ->has('previous')
            ->has('series', 31)
            ->has('presets', 7)
        );
});

it('shows the sales report split by payment method', function () {
    $this->get('/reports/sales?from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/sales')
            ->where('sales.revenue', 17600)
            ->where('sales.average_unit_price', 4400)
            ->where('paymentMethods.0.method', 'cash')
            ->where('paymentMethods.0.revenue', 17600)
            ->has('paymentMethods', 3)
        );
});

it('shows the purchase report with its supplier summary', function () {
    $this->get('/reports/purchases?from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/purchases')
            ->where('purchases.total', 20000)
            ->where('purchases.units', 10)
            ->where('suppliers.0.name', 'Northwind Textiles')
            ->where('suppliers.0.total', 20000)
        );
});

it('shows expenses by category with the period averages', function () {
    $this->get('/reports/expenses?from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/expenses')
            ->where('expenses.total', 6000)
            ->where('expenses.largest_category', 'Rent')
            // 60.00 over 31 days is 1.94 a day.
            ->where('averages.per_day', 194)
            ->has('series', 31)
        );
});

it('shows profit per product', function () {
    $this->get('/reports/products?from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/products')
            ->where('products.0.code', 'BLK-117')
            ->where('products.0.units', 4)
            ->where('products.0.gross_profit', 9600)
        );
});

it('shows what is on the shelf and what is not moving', function () {
    $idle = Product::factory()->create(['name' => 'Velvet', 'code' => 'VEL-117']);

    $purchase = Purchase::factory()->create([
        'invoiced_on' => '2026-03-01',
        'status' => PurchaseStatus::Draft,
    ]);
    $purchase->lines()->create([
        'product_id' => $idle->id,
        'quantity' => 2,
        'unit_cost' => Money::fromDecimal('15.00'),
        'discount' => Money::zero(),
    ]);
    app(PostPurchaseAction::class)->handle($purchase->refresh());

    $this->get('/reports/inventory?from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/inventory')
            ->where('inventory.total_value', 15000)
            ->where('inventory.dead_count', 1)
            ->where('inventory.dead_value', 3000)
        );
});

/*
|--------------------------------------------------------------------------
| The period contract every screen shares
|--------------------------------------------------------------------------
*/

it('defaults to the last 30 days when nothing is asked for', function () {
    $this->get('/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('period.preset', 'last_30_days')
            ->where('period.from', '2026-02-14')
            ->where('period.to', '2026-03-15')
        );
});

it('resolves a named preset', function () {
    $this->get('/reports?preset=this_month')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('period.from', '2026-03-01')
            ->where('period.to', '2026-03-15')
            ->where('period.label', 'This month')
        );
});

it('answers for the default period rather than failing on a mangled URL', function (string $query) {
    $this->get("/reports?{$query}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('period.preset', 'last_30_days'));
})->with([
    'a word for a date' => ['from=banana'],
    'a day that does not exist' => ['from=2026-02-31'],
    'an unknown preset' => ['preset=since_forever'],
    'an array where a string belongs' => ['from[]=2026-01-01'],
]);

it('answers for the same window on every report screen', function (string $path) {
    $this->get("{$path}?from=2026-03-01&to=2026-03-31")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('period.from', '2026-03-01')
            ->where('period.to', '2026-03-31')
            ->has('presets')
        );
})->with([
    '/reports',
    '/reports/sales',
    '/reports/purchases',
    '/reports/expenses',
    '/reports/products',
    '/reports/inventory',
    '/dashboard',
]);

it('keeps reports behind the login', function (string $path) {
    auth()->logout();

    $this->get($path)->assertRedirect('/login');
})->with([
    '/reports',
    '/reports/sales',
    '/reports/inventory',
    '/reports/summary/export',
]);

/*
|--------------------------------------------------------------------------
| The dashboard — P7.T11
|--------------------------------------------------------------------------
*/

it('shows the dashboard with its tiles, trend and recent activity', function () {
    $this->get('/dashboard?from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('profit.revenue', 17600)
            ->where('profit.net_profit', 3600)
            ->where('inventory.total_value', 12000)
            ->has('series', 31)
            ->has('topProducts', 1)
            ->has('recent.sales', 1)
            ->has('recent.purchases', 1)
            ->has('recent.expenses', 1)
        );
});

/*
|--------------------------------------------------------------------------
| CSV export — P7.T12
|--------------------------------------------------------------------------
*/

it('exports each report as a CSV named for its period', function (string $report, string $heading) {
    $response = $this->get("/reports/{$report}/export?from=2026-03-01&to=2026-03-31");

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload("{$report}-2026-03-01-to-2026-03-31.csv");

    expect($response->streamedContent())->toContain($heading);
})->with([
    ['summary', 'Net profit'],
    ['sales', 'Cost of goods sold'],
    ['purchases', 'Northwind Textiles'],
    ['expenses', 'Rent'],
    ['products', 'BLK-117'],
    ['inventory', 'On hand'],
]);

it('exports the same figures the screen shows', function () {
    $csv = $this->get('/reports/summary/export?from=2026-03-01&to=2026-03-31')
        ->streamedContent();

    expect($csv)->toContain('Revenue,176.00')
        ->toContain('Cost of goods sold,80.00')
        ->toContain('Gross profit,96.00')
        ->toContain('Expenses,60.00')
        ->toContain('Net profit,36.00');
});

it('refuses to export a report that does not exist', function () {
    $this->get('/reports/everything/export')->assertNotFound();
});
