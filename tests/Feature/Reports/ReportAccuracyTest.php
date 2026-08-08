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
use App\Models\SaleLine;
use App\Models\Supplier;
use App\Queries\ExpenseReportQuery;
use App\Queries\InventoryReportQuery;
use App\Queries\ProductProfitabilityQuery;
use App\Queries\ProfitReportQuery;
use App\Queries\PurchaseReportQuery;
use App\Queries\SalesReportQuery;
use App\Queries\SupplierSummaryQuery;
use App\Support\Money;
use App\Support\ReportPeriod;

/*
|--------------------------------------------------------------------------
| A fixture with figures worked out by hand
|--------------------------------------------------------------------------
|
| Every expectation below is a number someone calculated on paper first, so a
| failure means the report is wrong rather than that the test drifted with the
| code. The whole of Phase 7 rests on these being right — if gross profit here
| is wrong, every figure on every report screen is wrong with it.
|
| Bought:
|   05 Jan  Northwind   10 x BLK @ 18.00  + 20.00 freight  = 200.00  (20.00/unit)
|   20 Jan  Eastgate    10 x BLK @ 22.00                   = 220.00  (22.00/unit)
|   25 Jan  Eastgate     4 x VEL @ 15.00                   =  60.00  (15.00/unit)
|   10 Feb  Northwind    5 x SHR @ 30.00  + 10.00 duty     = 160.00  (32.00/unit)
|
| Sold:
|   01 Feb  12 x BLK @ 44.00            = 528.00 taking, 244.00 cost (10@20 + 2@22)
|   15 Feb   3 x SHR @ 60.00 less 10.00 = 170.00 taking,  96.00 cost (3@32)
|
| Spent (February): rent 150.00, transport 40.00, marketing 30.00 = 220.00
|
*/

beforeEach(function () {
    $this->northwind = Supplier::factory()->create(['name' => 'Northwind Textiles']);
    $this->eastgate = Supplier::factory()->create(['name' => 'Eastgate Supply']);

    $this->blackout = Product::factory()->create(['name' => 'Blackout 117x137', 'code' => 'BLK-117']);
    $this->sheer = Product::factory()->create(['name' => 'Sheer 168x183', 'code' => 'SHR-168']);
    $this->velvet = Product::factory()->create(['name' => 'Velvet 117x229', 'code' => 'VEL-117']);

    postPurchase($this->northwind, '2026-01-05', [
        [$this->blackout, 10, '18.00'],
    ], [['Freight', '20.00', CostAllocationMethod::ByQuantity]]);

    postPurchase($this->eastgate, '2026-01-20', [[$this->blackout, 10, '22.00']]);
    postPurchase($this->eastgate, '2026-01-25', [[$this->velvet, 4, '15.00']]);

    postPurchase($this->northwind, '2026-02-10', [
        [$this->sheer, 5, '30.00'],
    ], [['Duty', '10.00', CostAllocationMethod::ByValue]]);

    postSale('2026-02-01', [[$this->blackout, 12, '44.00']]);
    postSale('2026-02-15', [[$this->sheer, 3, '60.00', '10.00']]);

    spend('Rent', '150.00', '2026-02-01');
    spend('Transport', '40.00', '2026-02-14');
    spend('Marketing', '30.00', '2026-02-20');

    $this->january = ReportPeriod::between('2026-01-01', '2026-01-31');
    $this->february = ReportPeriod::between('2026-02-01', '2026-02-28');
});

/**
 * @param  list<array{0: Product, 1: int, 2: string}>  $lines
 * @param  list<array{0: string, 1: string, 2: CostAllocationMethod}>  $costs
 */
function postPurchase(Supplier $supplier, string $on, array $lines, array $costs = []): Purchase
{
    $purchase = Purchase::factory()->for($supplier)->create([
        'invoiced_on' => $on,
        'status' => PurchaseStatus::Draft,
    ]);

    foreach ($lines as [$product, $quantity, $unitCost]) {
        $purchase->lines()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_cost' => Money::fromDecimal($unitCost),
            'discount' => Money::zero(),
        ]);
    }

    foreach ($costs as [$label, $amount, $method]) {
        $purchase->additionalCosts()->create([
            'label' => $label,
            'amount' => Money::fromDecimal($amount),
            'allocation_method' => $method,
        ]);
    }

    return app(PostPurchaseAction::class)->handle($purchase->refresh());
}

/**
 * @param  list<array{0: Product, 1: int, 2: string, 3?: string}>  $lines
 */
function postSale(string $on, array $lines): Sale
{
    $sale = Sale::factory()->create([
        'sold_on' => $on,
        'status' => SaleStatus::Draft,
        'payment_method' => PaymentMethod::Cash,
    ]);

    foreach ($lines as $line) {
        $sale->lines()->create([
            'product_id' => $line[0]->id,
            'quantity' => $line[1],
            'unit_price' => Money::fromDecimal($line[2]),
            'discount' => Money::fromDecimal($line[3] ?? '0'),
        ]);
    }

    return app(PostSaleAction::class)->handle($sale->refresh());
}

function spend(string $category, string $amount, string $on): Expense
{
    return Expense::factory()->create([
        'expense_category_id' => ExpenseCategory::query()->firstOrCreate(['name' => $category])->id,
        'amount' => Money::fromDecimal($amount),
        'spent_on' => $on,
        'payment_method' => PaymentMethod::Transfer,
    ]);
}

/*
|--------------------------------------------------------------------------
| Sales — P7.T3
|--------------------------------------------------------------------------
*/

it('reports February takings, cost and profit', function () {
    $report = app(SalesReportQuery::class)->get($this->february);

    expect($report['revenue']->toDecimal())->toBe('698.00')
        ->and($report['cost_of_goods_sold']->toDecimal())->toBe('340.00')
        ->and($report['gross_profit']->toDecimal())->toBe('358.00')
        ->and($report['invoice_count'])->toBe(2)
        ->and($report['units'])->toBe(15);
});

it('averages a sale over its invoices and units', function () {
    $report = app(SalesReportQuery::class)->get($this->february);

    // 698.00 over 2 invoices, and over 15 units.
    expect($report['average_invoice']->toDecimal())->toBe('349.00')
        ->and($report['average_unit_price']->toDecimal())->toBe('46.53')
        ->and($report['average_unit_cost']->toDecimal())->toBe('22.67')
        ->and($report['average_unit_profit']->toDecimal())->toBe('23.87');
});

it('leaves draft sales out of the takings', function () {
    $sale = Sale::factory()->create(['sold_on' => '2026-02-20', 'status' => SaleStatus::Draft]);
    $sale->lines()->create([
        'product_id' => $this->blackout->id,
        'quantity' => 5,
        'unit_price' => Money::fromDecimal('44.00'),
        'discount' => Money::zero(),
    ]);

    expect(app(SalesReportQuery::class)->get($this->february)['revenue']->toDecimal())
        ->toBe('698.00');
});

it('counts nothing outside the period', function () {
    $report = app(SalesReportQuery::class)->get($this->january);

    expect($report['revenue']->isZero())->toBeTrue()
        ->and($report['cost_of_goods_sold']->isZero())->toBeTrue()
        ->and($report['invoice_count'])->toBe(0)
        ->and($report['average_unit_price']->isZero())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The acceptance case from ROADMAP.md
|--------------------------------------------------------------------------
|
| "Gross profit from the report equals the sum of per-line profits computed
| independently" — the per-line figure being read straight off the models,
| which take a different route to the answer entirely.
|
*/

it('agrees with the profit worked out line by line', function () {
    $byLine = Money::sum(
        ...Sale::query()
            ->where('status', SaleStatus::Posted)
            ->whereDate('sold_on', '>=', '2026-02-01')
            ->whereDate('sold_on', '<=', '2026-02-28')
            ->with('lines')
            ->get()
            ->flatMap(fn (Sale $sale) => $sale->lines)
            ->map(fn (SaleLine $line): Money => $line->grossProfit())
    );

    expect(app(SalesReportQuery::class)->get($this->february)['gross_profit']->toDecimal())
        ->toBe($byLine->toDecimal())
        ->toBe('358.00');
});

/*
|--------------------------------------------------------------------------
| Purchases — P7.T4
|--------------------------------------------------------------------------
*/

it('reports January buying, freight included', function () {
    $report = app(PurchaseReportQuery::class)->get($this->january);

    expect($report['goods']->toDecimal())->toBe('460.00')
        ->and($report['additional_costs']->toDecimal())->toBe('20.00')
        ->and($report['total']->toDecimal())->toBe('480.00')
        ->and($report['invoice_count'])->toBe(3)
        ->and($report['supplier_count'])->toBe(2)
        ->and($report['units'])->toBe(24)
        ->and($report['average_invoice']->toDecimal())->toBe('160.00')
        ->and($report['average_unit_cost']->toDecimal())->toBe('20.00');
});

it('leaves draft purchases out of the buying', function () {
    $purchase = Purchase::factory()->create([
        'invoiced_on' => '2026-01-15',
        'status' => PurchaseStatus::Draft,
    ]);
    $purchase->lines()->create([
        'product_id' => $this->blackout->id,
        'quantity' => 100,
        'unit_cost' => Money::fromDecimal('18.00'),
        'discount' => Money::zero(),
    ]);

    expect(app(PurchaseReportQuery::class)->get($this->january)['total']->toDecimal())
        ->toBe('480.00');
});

/*
|--------------------------------------------------------------------------
| Expenses — P7.T5
|--------------------------------------------------------------------------
*/

it('totals February expenses by category, biggest first', function () {
    $report = app(ExpenseReportQuery::class)->get($this->february);

    expect($report['total']->toDecimal())->toBe('220.00')
        ->and($report['count'])->toBe(3)
        ->and($report['largest_category'])->toBe('Rent');

    $spent = collect($report['categories'])
        ->filter(fn (array $category): bool => $category['count'] > 0)
        ->values();

    expect($spent->pluck('name')->all())->toBe(['Rent', 'Transport', 'Marketing'])
        ->and($spent->pluck('total')->map->toDecimal()->all())
        ->toBe(['150.00', '40.00', '30.00']);
});

it('lists a category nothing was spent on rather than dropping it', function () {
    ExpenseCategory::query()->create(['name' => 'Utilities']);

    $names = array_column(app(ExpenseReportQuery::class)->get($this->february)['categories'], 'name');

    expect($names)->toContain('Utilities');
});

/*
|--------------------------------------------------------------------------
| Profit — P7.T6 — and the averages — P7.T10
|--------------------------------------------------------------------------
*/

it('subtracts expenses only at the very end', function () {
    $report = app(ProfitReportQuery::class)->get($this->february);

    expect($report['revenue']->toDecimal())->toBe('698.00')
        ->and($report['cost_of_goods_sold']->toDecimal())->toBe('340.00')
        ->and($report['gross_profit']->toDecimal())->toBe('358.00')
        ->and($report['expenses']->toDecimal())->toBe('220.00')
        ->and($report['net_profit']->toDecimal())->toBe('138.00');
});

it('never lets buying stock reach the profit', function () {
    // February bought 160.00 of goods. If that leaked into the accounts, net
    // profit would read -22.00 instead of 138.00.
    expect(app(ProfitReportQuery::class)->get($this->february)['net_profit']->toDecimal())
        ->toBe('138.00');
});

it('averages income, outcome and profit over the period', function () {
    $averages = app(ProfitReportQuery::class)->get($this->february)['averages'];

    // 28 days in the period; a month is the mean 30.4375 days.
    expect($averages['income']['per_day']->toDecimal())->toBe('24.93')
        ->and($averages['income']['per_week']->toDecimal())->toBe('174.50')
        ->and($averages['income']['per_month']->toDecimal())->toBe('758.76')
        ->and($averages['outcome']['per_day']->toDecimal())->toBe('20.00')
        ->and($averages['outcome']['per_week']->toDecimal())->toBe('140.00')
        ->and($averages['outcome']['per_month']->toDecimal())->toBe('608.75')
        ->and($averages['net_profit']['per_day']->toDecimal())->toBe('4.93')
        ->and($averages['net_profit']['per_week']->toDecimal())->toBe('34.50');
});

it('builds a daily series that adds back to the period total', function () {
    $series = app(ProfitReportQuery::class)->series($this->february);

    expect($series)->toHaveCount(28)
        ->and(array_sum(array_column($series, 'revenue')))->toBe(69800)
        ->and(array_sum(array_column($series, 'cost_of_goods_sold')))->toBe(34000)
        ->and(array_sum(array_column($series, 'expenses')))->toBe(22000)
        ->and(array_sum(array_column($series, 'net_profit')))->toBe(13800);

    $first = collect($series)->firstWhere('bucket', '2026-02-01');

    // 528.00 taken, 244.00 of cost, 150.00 of rent on the same day.
    expect($first['revenue'])->toBe(52800)
        ->and($first['cost_of_goods_sold'])->toBe(24400)
        ->and($first['expenses'])->toBe(15000)
        ->and($first['net_profit'])->toBe(13400);
});

/*
|--------------------------------------------------------------------------
| Product profitability — P7.T7
|--------------------------------------------------------------------------
*/

it('reports what each product made, most profitable first', function () {
    $rows = app(ProductProfitabilityQuery::class)->get($this->february);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['code'])->toBe('BLK-117')
        ->and($rows[0]['units'])->toBe(12)
        ->and($rows[0]['revenue']->toDecimal())->toBe('528.00')
        ->and($rows[0]['cost_of_goods_sold']->toDecimal())->toBe('244.00')
        ->and($rows[0]['gross_profit']->toDecimal())->toBe('284.00')
        ->and($rows[0]['average_unit_cost']->toDecimal())->toBe('20.33')
        ->and($rows[1]['code'])->toBe('SHR-168')
        ->and($rows[1]['revenue']->toDecimal())->toBe('170.00')
        ->and($rows[1]['gross_profit']->toDecimal())->toBe('74.00');
});

it('costs a product from the batches it actually drew on', function () {
    // The blackout sale crossed two batches — ten at 20.00 landed and two at
    // 22.00. An average cost would have said 21.00 a unit and 252.00 in all.
    $rows = app(ProductProfitabilityQuery::class)->get($this->february);

    expect($rows[0]['cost_of_goods_sold']->toDecimal())->toBe('244.00');
});

/*
|--------------------------------------------------------------------------
| Suppliers — P7.T8
|--------------------------------------------------------------------------
*/

it('summarises January by supplier, biggest spend first', function () {
    $rows = app(SupplierSummaryQuery::class)->get($this->january);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['name'])->toBe('Eastgate Supply')
        ->and($rows[0]['invoice_count'])->toBe(2)
        ->and($rows[0]['units'])->toBe(14)
        ->and($rows[0]['total']->toDecimal())->toBe('280.00')
        ->and($rows[0]['average_invoice']->toDecimal())->toBe('140.00')
        ->and($rows[0]['last_invoiced_on'])->toBe('2026-01-25')
        ->and($rows[1]['name'])->toBe('Northwind Textiles')
        ->and($rows[1]['goods']->toDecimal())->toBe('180.00')
        ->and($rows[1]['additional_costs']->toDecimal())->toBe('20.00')
        ->and($rows[1]['total']->toDecimal())->toBe('200.00');
});

/*
|--------------------------------------------------------------------------
| Inventory and dead stock — P7.T9
|--------------------------------------------------------------------------
*/

it('values the shelf as it stood at the end of the period', function () {
    $report = app(InventoryReportQuery::class)->get($this->february);

    // 8 blackout left at 22.00, 2 sheer at 32.00, 4 velvet at 15.00.
    expect($report['total_value']->toDecimal())->toBe('300.00')
        ->and($report['total_units'])->toBe(14)
        ->and($report['stocked_count'])->toBe(3);
});

it('finds the stock that did not move', function () {
    $report = app(InventoryReportQuery::class)->get($this->february);

    expect($report['dead_count'])->toBe(1)
        ->and($report['dead_units'])->toBe(4)
        ->and($report['dead_value']->toDecimal())->toBe('60.00');

    $dead = collect($report['products'])->firstWhere('is_dead', true);

    expect($dead['code'])->toBe('VEL-117')
        ->and($dead['units_sold'])->toBe(0)
        ->and($dead['last_sold_on'])->toBeNull();
});

it('rewinds the valuation rather than reporting today', function () {
    // At the end of January nothing had sold and the February receipt had not
    // happened: 10 at 20.00, 10 at 22.00 and 4 at 15.00.
    $report = app(InventoryReportQuery::class)->get($this->january);

    expect($report['total_value']->toDecimal())->toBe('480.00')
        ->and($report['total_units'])->toBe(24);
});
