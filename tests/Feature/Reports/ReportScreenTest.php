<?php

use App\Actions\Purchasing\PostPurchaseAction;
use App\Actions\Sales\PostSaleAction;
use App\Enums\CostAllocationMethod;
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
        'title' => 'March rent',
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
| The screen
|--------------------------------------------------------------------------
|
| 176.00 taken, 200.00 of stock bought and 60.00 of rent paid, so 84.00 more
| went out than came in.
|
*/

it('shows money in, money out and what is left for the requested period', function () {
    $this->get('/reports?from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->where('period.from', '2026-03-01')
            ->where('period.to', '2026-03-31')
            ->where('cashFlow.income', 17600)
            ->where('cashFlow.purchases', 20000)
            ->where('cashFlow.expenses', 6000)
            ->where('cashFlow.outcome', 26000)
            ->where('cashFlow.net', -8400)
            ->where('cashFlow.days', 31)
            ->has('previous')
            ->has('presets', 7)
        );
});

it('sends the averages the net tile and the export are built on', function () {
    $this->get('/reports?from=2026-03-01&to=2026-03-31')
        ->assertInertia(fn ($page) => $page
            // 176.00 over 31 days is 5.68 a day; 84.00 out is 2.71 a day.
            ->where('cashFlow.averages.income.per_day', 568)
            ->where('cashFlow.averages.outcome.per_day', 839)
            ->where('cashFlow.averages.net.per_day', -271)
            ->has('cashFlow.averages.net.per_week')
            ->has('cashFlow.averages.net.per_month')
        );
});

/*
|--------------------------------------------------------------------------
| The documents behind the figures
|--------------------------------------------------------------------------
|
| Each tab lists the documents its total was added up from, so a row on screen
| and the tile above it can never describe different things.
|
*/

it('lists every document behind the figures, by kind', function () {
    $this->get('/reports?from=2026-03-01&to=2026-03-31')
        ->assertInertia(fn ($page) => $page
            ->has('activity.sales', 1, fn ($row) => $row
                ->where('kind', 'sale')
                ->where('date', '2026-03-05')
                ->where('label', 'SAL-00001')
                ->where('detail', 'Cash')
                ->where('draft', false)
                ->where('total', 17600)
                ->etc()
            )
            ->has('activity.purchases', 1, fn ($row) => $row
                ->where('kind', 'purchase')
                ->where('date', '2026-03-01')
                ->where('detail', 'Northwind Textiles')
                ->where('total', 20000)
                ->etc()
            )
            ->has('activity.expenses', 1, fn ($row) => $row
                ->where('kind', 'expense')
                ->where('date', '2026-03-02')
                ->where('label', 'March rent')
                ->where('detail', 'Rent')
                ->where('total', 6000)
                ->etc()
            )
        );
});

it('leaves documents outside the period out of the lists', function () {
    $this->get('/reports?from=2026-03-10&to=2026-03-31')
        ->assertInertia(fn ($page) => $page
            ->has('activity.sales', 0)
            ->has('activity.purchases', 0)
            ->has('activity.expenses', 0)
        );
});

it('leaves drafts out of the report, since no total counts them', function () {
    Sale::factory()->create([
        'sold_on' => '2026-03-06',
        'status' => SaleStatus::Draft,
        'payment_method' => PaymentMethod::Cash,
    ]);

    $this->get('/reports?from=2026-03-01&to=2026-03-31')
        ->assertInertia(fn ($page) => $page
            ->has('activity.sales', 1)
            ->where('activity.sales.0.label', 'SAL-00001')
        );
});

it('counts freight and duty in what an invoice came to', function () {
    Purchase::query()->first()->additionalCosts()->create([
        'label' => 'Freight',
        'amount' => Money::fromDecimal('15.00'),
        'allocation_method' => CostAllocationMethod::ByValue,
    ]);

    $this->get('/reports?from=2026-03-01&to=2026-03-31')
        ->assertInertia(fn ($page) => $page->where('activity.purchases.0.total', 21500));
});

it('has retired the report screens it used to split this across', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    '/reports/sales',
    '/reports/purchases',
    '/reports/expenses',
    '/reports/products',
    '/reports/inventory',
]);

/*
|--------------------------------------------------------------------------
| The period contract
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

it('answers for the same window on the report and the dashboard', function (string $path) {
    $this->get("{$path}?from=2026-03-01&to=2026-03-31")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('period.from', '2026-03-01')
            ->where('period.to', '2026-03-31')
            ->where('cashFlow.net', -8400)
            ->has('presets')
        );
})->with(['/reports', '/dashboard']);

it('keeps reports behind the login', function (string $path) {
    auth()->logout();

    $this->get($path)->assertRedirect('/login');
})->with(['/reports', '/reports/export']);

/*
|--------------------------------------------------------------------------
| The dashboard
|--------------------------------------------------------------------------
*/

it('shows the dashboard with the same figures and recent activity', function () {
    $this->get('/dashboard?from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('cashFlow.income', 17600)
            ->where('cashFlow.outcome', 26000)
            ->has('previous')
            ->has('recent.sales', 1)
            ->has('recent.purchases', 1)
            ->has('recent.expenses', 1)
        );
});

it('lists drafts on the dashboard, where they are work still to do', function () {
    Sale::factory()->create([
        'sold_on' => '2026-03-06',
        'status' => SaleStatus::Draft,
        'payment_method' => PaymentMethod::Cash,
    ]);

    $this->get('/dashboard?from=2026-03-01&to=2026-03-31')
        ->assertInertia(fn ($page) => $page
            ->has('recent.sales', 2)
            // Newest first, and a draft invoice comes to nothing until it has
            // lines on it.
            ->where('recent.sales.0.draft', true)
            ->where('recent.sales.0.total', 0)
            ->where('recent.sales.1.draft', false)
        );
});

it('shows only the latest few documents of each kind on the dashboard', function () {
    // One at a time: the factory reads the next number off the table, so a
    // batch would give them all the same one.
    for ($i = 0; $i < 8; $i++) {
        Sale::factory()->create([
            'sold_on' => '2026-03-07',
            'status' => SaleStatus::Posted,
            'payment_method' => PaymentMethod::Cash,
        ]);
    }

    $this->get('/dashboard?from=2026-03-01&to=2026-03-31')
        ->assertInertia(fn ($page) => $page->has('recent.sales', 6));

    $this->get('/reports?from=2026-03-01&to=2026-03-31')
        ->assertInertia(fn ($page) => $page->has('activity.sales', 9));
});

/*
|--------------------------------------------------------------------------
| CSV export
|--------------------------------------------------------------------------
*/

it('exports the report as a CSV named for its period', function () {
    $response = $this->get('/reports/export?from=2026-03-01&to=2026-03-31');

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('report-2026-03-01-to-2026-03-31.csv');
});

it('exports the same figures the screen shows', function () {
    $csv = $this->get('/reports/export?from=2026-03-01&to=2026-03-31')
        ->streamedContent();

    expect($csv)->toContain('Income,176.00')
        ->toContain('Purchases,200.00')
        ->toContain('Expenses,60.00')
        ->toContain('Outcome,260.00')
        ->toContain('Net,-84.00')
        ->toContain('Average net per day,-2.71');
});
