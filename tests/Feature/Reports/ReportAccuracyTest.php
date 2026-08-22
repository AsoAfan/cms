<?php

use App\Actions\Purchasing\ReceivePurchaseAction;
use App\Actions\Sales\IssueSaleAction;
use App\Enums\CostAllocationMethod;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Queries\CashFlowQuery;
use App\Support\Money;
use App\Support\ReportPeriod;

/*
|--------------------------------------------------------------------------
| A fixture with figures worked out by hand
|--------------------------------------------------------------------------
|
| Every expectation below is a number someone calculated on paper first, so a
| failure means the report is wrong rather than that the test drifted with the
| code.
|
| Bought:
|   05 Jan  Northwind   10 x BLK @ 18.00  + 20.00 freight  = 200.00
|   20 Jan  Eastgate    10 x BLK @ 22.00                   = 220.00
|   25 Jan  Eastgate     4 x VEL @ 15.00                   =  60.00
|   10 Feb  Northwind    5 x SHR @ 30.00  + 10.00 duty     = 160.00
|
| Sold:
|   01 Feb  12 x BLK @ 44.00            = 528.00 taken
|   15 Feb   3 x SHR @ 60.00 less 10.00 = 170.00 taken
|
| Spent (February): rent 150.00, transport 40.00, marketing 30.00 = 220.00
|
| So January is 0.00 in and 480.00 out; February is 698.00 in and 380.00 out.
|
*/

beforeEach(function () {
    $this->blackout = Product::factory()->create(['name' => 'Blackout 117x137']);
    $this->sheer = Product::factory()->create(['name' => 'Sheer 168x183']);
    $this->velvet = Product::factory()->create(['name' => 'Velvet 117x229']);

    arrivedPurchase('2026-01-05', [
        [$this->blackout, 10, '18.00'],
    ], [['Freight', '20.00', CostAllocationMethod::ByQuantity]]);

    arrivedPurchase('2026-01-20', [[$this->blackout, 10, '22.00']]);
    arrivedPurchase('2026-01-25', [[$this->velvet, 4, '15.00']]);

    arrivedPurchase('2026-02-10', [
        [$this->sheer, 5, '30.00'],
    ], [['Duty', '10.00', CostAllocationMethod::ByValue]]);

    deliveredSale('2026-02-01', [[$this->blackout, 12, '44.00']]);
    deliveredSale('2026-02-15', [[$this->sheer, 3, '60.00', '10.00']]);

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
function arrivedPurchase(string $on, array $lines, array $costs = []): Purchase
{
    $purchase = Purchase::factory()->arrived()->create([
        'invoiced_on' => $on,
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

    return app(ReceivePurchaseAction::class)->handle($purchase->refresh());
}

/**
 * @param  list<array{0: Product, 1: int, 2: string, 3?: string}>  $lines
 */
function deliveredSale(string $on, array $lines): Sale
{
    $sale = Sale::factory()->delivered()->create([
        'sold_on' => $on,
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

    return app(IssueSaleAction::class)->handle($sale->refresh());
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

function cashFlow(ReportPeriod $period): array
{
    return app(CashFlowQuery::class)->get($period);
}

/*
|--------------------------------------------------------------------------
| Money in, money out
|--------------------------------------------------------------------------
*/

it('reports what came in and what went out in February', function () {
    $report = cashFlow($this->february);

    expect($report['income']->toDecimal())->toBe('698.00')
        ->and($report['purchases']->toDecimal())->toBe('160.00')
        ->and($report['expenses']->toDecimal())->toBe('220.00')
        ->and($report['outcome']->toDecimal())->toBe('380.00')
        ->and($report['net']->toDecimal())->toBe('318.00');
});

it('reads a month of stocking up as money out with nothing in', function () {
    // The whole of January was buying: 200.00 + 220.00 + 60.00, nothing sold.
    // On a cash view that is a loss, and it is meant to be — the money has
    // gone, and the goods are on the shelf rather than in the figures.
    $report = cashFlow($this->january);

    expect($report['income']->isZero())->toBeTrue()
        ->and($report['outcome']->toDecimal())->toBe('480.00')
        ->and($report['net']->toDecimal())->toBe('-480.00');
});

it('counts what was bought, never what was sold off the shelf', function () {
    // February's sales consumed 340.00 of stock, almost all of it bought in
    // January. Cost of goods sold has no place in a cash view, so February's
    // outcome is the 160.00 invoiced that month plus the 220.00 spent.
    expect(cashFlow($this->february)['outcome']->toDecimal())->toBe('380.00');
});

it('takes freight and duty as money out alongside the goods', function () {
    // 180.00 of curtains and 20.00 of freight on the 5th, 220.00 on the 20th,
    // 60.00 on the 25th.
    expect(cashFlow(ReportPeriod::between('2026-01-01', '2026-01-05'))['outcome']->toDecimal())
        ->toBe('200.00');
});

it('takes a discount off what came in', function () {
    // 3 x 60.00 less 10.00 off the invoice.
    expect(cashFlow(ReportPeriod::between('2026-02-15', '2026-02-15'))['income']->toDecimal())
        ->toBe('170.00');
});

/*
|--------------------------------------------------------------------------
| What counts, and when
|--------------------------------------------------------------------------
*/

it('leaves documents whose goods have not moved out of both sides', function () {
    $sale = Sale::factory()->create(['sold_on' => '2026-02-20', 'status' => SaleStatus::Ordered]);
    $sale->lines()->create([
        'product_id' => $this->blackout->id,
        'quantity' => 5,
        'unit_price' => Money::fromDecimal('44.00'),
        'discount' => Money::zero(),
    ]);

    $purchase = Purchase::factory()->create([
        'invoiced_on' => '2026-02-20',
        'status' => PurchaseStatus::OnTheWay,
    ]);
    $purchase->lines()->create([
        'product_id' => $this->velvet->id,
        'quantity' => 8,
        'unit_cost' => Money::fromDecimal('15.00'),
        'discount' => Money::zero(),
    ]);

    $report = cashFlow($this->february);

    expect($report['income']->toDecimal())->toBe('698.00')
        ->and($report['outcome']->toDecimal())->toBe('380.00');
});

it('scopes each side by the document date, including the last day', function (string $from, string $to, string $income, string $outcome) {
    $report = cashFlow(ReportPeriod::between($from, $to));

    expect($report['income']->toDecimal())->toBe($income)
        ->and($report['outcome']->toDecimal())->toBe($outcome);
})->with([
    // The 10 Feb purchase and the 1 Feb sale and rent, on the closing day.
    'up to the purchase' => ['2026-02-01', '2026-02-10', '528.00', '310.00'],
    // One day earlier and the purchase is not in it.
    'up to the day before' => ['2026-02-01', '2026-02-09', '528.00', '150.00'],
    'a single day of expenses' => ['2026-02-14', '2026-02-14', '0.00', '40.00'],
]);

it('reports nothing for a period with no documents in it', function () {
    $report = cashFlow(ReportPeriod::between('2026-03-01', '2026-03-31'));

    expect($report['income']->isZero())->toBeTrue()
        ->and($report['outcome']->isZero())->toBeTrue()
        ->and($report['net']->isZero())->toBeTrue()
        ->and($report['averages']['net']['per_day']->isZero())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Averages
|--------------------------------------------------------------------------
*/

it('spreads each figure over the days in the period', function () {
    // February 2026 is 28 days. 698.00 in, 380.00 out, 318.00 left.
    $averages = cashFlow($this->february)['averages'];

    expect($averages['income']['per_day']->toDecimal())->toBe('24.93')
        ->and($averages['outcome']['per_day']->toDecimal())->toBe('13.57')
        ->and($averages['net']['per_day']->toDecimal())->toBe('11.36')
        ->and($averages['income']['per_week']->toDecimal())->toBe('174.50')
        ->and($averages['outcome']['per_week']->toDecimal())->toBe('95.00')
        ->and($averages['net']['per_week']->toDecimal())->toBe('79.50');
});

it('scales a month by the mean 30.4375 days, not by thirty', function () {
    // 318.00 over 28 days is 11.357/day; times 30.4375 is 345.68.
    expect(cashFlow($this->february)['averages']['net']['per_month']->toDecimal())
        ->toBe('345.68');
});

it('keeps the three averages reconciling with each other', function () {
    $averages = cashFlow($this->february)['averages'];

    expect($averages['income']['per_week']->minus($averages['outcome']['per_week'])->toDecimal())
        ->toBe($averages['net']['per_week']->toDecimal());
});

it('averages a loss as a loss', function () {
    // 480.00 out over the 31 days of January.
    expect(cashFlow($this->january)['averages']['net']['per_day']->toDecimal())
        ->toBe('-15.48');
});
