<?php

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockBatchConsumption;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Queries\InventoryValuationQuery;
use App\Queries\StockOnHandQuery;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->service = app(InventoryService::class);
    $this->onHand = app(StockOnHandQuery::class);
    $this->valuation = app(InventoryValuationQuery::class);
    $this->product = Product::factory()->create(['name' => 'Blackout 117x137']);
});

function receive(int $quantity, string $unitCost, string $on): StockMovement
{
    return test()->service->receive(
        product: test()->product,
        quantity: $quantity,
        unitCost: Money::fromDecimal($unitCost),
        type: StockMovementType::Purchase,
        occurredAt: Carbon::parse($on),
    );
}

function issue(int $quantity, string $on): StockMovement
{
    return test()->service->issue(
        product: test()->product,
        quantity: $quantity,
        type: StockMovementType::Sale,
        occurredAt: Carbon::parse($on),
    );
}

/*
|--------------------------------------------------------------------------
| The acceptance case from ROADMAP.md
|--------------------------------------------------------------------------
*/

it('buys 10 at $5 then 10 at $7, issues 15, and lands COGS of exactly $85', function () {
    receive(10, '5.00', '2026-01-01');
    receive(10, '7.00', '2026-01-02');

    $sale = issue(15, '2026-01-03');

    expect($sale->costOfGoodsSold()->toDecimal())->toBe('85.00')
        ->and($this->onHand->forProduct($this->product))->toBe(5)
        ->and($this->valuation->forProduct($this->product)->toDecimal())->toBe('35.00');
});

/*
|--------------------------------------------------------------------------
| Receiving
|--------------------------------------------------------------------------
*/

it('records a receipt as a movement and a batch', function () {
    $movement = receive(10, '5.00', '2026-01-01');

    expect($movement->quantity)->toBe(10)
        ->and($movement->type)->toBe(StockMovementType::Purchase)
        ->and($movement->isReceipt())->toBeTrue()
        ->and($movement->batches)->toHaveCount(1)
        ->and($movement->batches->first()->quantity_received)->toBe(10)
        ->and($movement->batches->first()->unit_cost->toDecimal())->toBe('5.00');
});

it('refuses a receipt of nothing or less', function (int $quantity) {
    expect(fn () => receive($quantity, '5.00', '2026-01-01'))
        ->toThrow(InvalidArgumentException::class);

    expect(StockMovement::query()->count())->toBe(0);
})->with([0, -1]);

it('refuses a negative unit cost', function () {
    expect(fn () => receive(10, '-1.00', '2026-01-01'))
        ->toThrow(InvalidArgumentException::class);
});

it('accepts a free receipt', function () {
    receive(10, '0.00', '2026-01-01');

    expect($this->valuation->forProduct($this->product)->isZero())->toBeTrue()
        ->and($this->onHand->forProduct($this->product))->toBe(10);
});

/*
|--------------------------------------------------------------------------
| FIFO consumption
|--------------------------------------------------------------------------
*/

it('takes from the oldest batch first', function () {
    $old = receive(10, '5.00', '2026-01-01');
    receive(10, '7.00', '2026-01-02');

    $sale = issue(4, '2026-01-03');

    expect($sale->costOfGoodsSold()->toDecimal())->toBe('20.00')
        ->and($sale->consumptions)->toHaveCount(1)
        ->and($sale->consumptions->first()->stock_batch_id)->toBe($old->batches->first()->id);
});

it('leaves the rest of a partly consumed batch available', function () {
    receive(10, '5.00', '2026-01-01');

    issue(4, '2026-01-02');
    $second = issue(4, '2026-01-03');

    expect($second->costOfGoodsSold()->toDecimal())->toBe('20.00')
        ->and($this->onHand->forProduct($this->product))->toBe(2);
});

it('spans as many batches as it needs', function () {
    receive(2, '1.00', '2026-01-01');
    receive(2, '2.00', '2026-01-02');
    receive(2, '3.00', '2026-01-03');

    $sale = issue(5, '2026-01-04');

    // 2 at 1.00 + 2 at 2.00 + 1 at 3.00
    expect($sale->costOfGoodsSold()->toDecimal())->toBe('9.00')
        ->and($sale->consumptions)->toHaveCount(3)
        ->and($sale->consumptions->sum('quantity'))->toBe(5);
});

it('empties a batch exactly without touching the next', function () {
    receive(10, '5.00', '2026-01-01');
    receive(10, '7.00', '2026-01-02');

    $sale = issue(10, '2026-01-03');

    expect($sale->costOfGoodsSold()->toDecimal())->toBe('50.00')
        ->and($sale->consumptions)->toHaveCount(1)
        ->and($this->valuation->forProduct($this->product)->toDecimal())->toBe('70.00');
});

it('breaks ties between same-day batches by insertion order', function () {
    $first = receive(5, '4.00', '2026-01-01 09:00');
    receive(5, '9.00', '2026-01-01 09:00');

    $sale = issue(5, '2026-01-01 17:00');

    expect($sale->consumptions->first()->stock_batch_id)->toBe($first->batches->first()->id)
        ->and($sale->costOfGoodsSold()->toDecimal())->toBe('20.00');
});

it('costs a long run of small batches exactly', function () {
    foreach (range(1, 10) as $day) {
        receive(3, sprintf('%d.33', $day), sprintf('2026-01-%02d', $day));
    }

    $sale = issue(25, '2026-02-01');

    // 3 each from days 1-8 (24 units), then 1 from day 9.
    $expected = 0;

    foreach (range(1, 8) as $day) {
        $expected += 3 * ($day * 100 + 33);
    }

    $expected += 9 * 100 + 33;

    expect($sale->costOfGoodsSold()->minorUnits)->toBe($expected)
        ->and($this->onHand->forProduct($this->product))->toBe(5);
});

/*
|--------------------------------------------------------------------------
| Dates
|--------------------------------------------------------------------------
*/

it('will not consume stock that had not arrived yet', function () {
    receive(10, '5.00', '2026-01-10');

    expect(fn () => issue(1, '2026-01-05'))
        ->toThrow(InsufficientStockException::class);
});

it('leaves settled allocations alone when a receipt is back-dated afterwards', function () {
    receive(10, '7.00', '2026-02-01');
    $sale = issue(10, '2026-02-02');

    expect($sale->costOfGoodsSold()->toDecimal())->toBe('70.00');

    // A delivery note for January turns up late. The ledger is append-only:
    // what the February sale already cost does not change retrospectively.
    receive(10, '5.00', '2026-01-01');

    expect($sale->fresh()->costOfGoodsSold()->toDecimal())->toBe('70.00')
        ->and($this->onHand->forProduct($this->product))->toBe(10);
});

it('reports stock as it stood on a past date', function () {
    receive(10, '5.00', '2026-01-01');
    issue(4, '2026-01-05');
    receive(10, '7.00', '2026-01-10');

    expect($this->onHand->forProduct($this->product, Carbon::parse('2026-01-01')))->toBe(10)
        ->and($this->onHand->forProduct($this->product, Carbon::parse('2026-01-05')))->toBe(6)
        ->and($this->onHand->forProduct($this->product, Carbon::parse('2026-01-10')))->toBe(16)
        ->and($this->onHand->forProduct($this->product))->toBe(16);
});

it('values stock as it stood on a past date', function () {
    receive(10, '5.00', '2026-01-01');
    issue(4, '2026-01-05');
    receive(10, '7.00', '2026-01-10');

    expect($this->valuation->forProduct($this->product, Carbon::parse('2026-01-01'))->toDecimal())->toBe('50.00')
        ->and($this->valuation->forProduct($this->product, Carbon::parse('2026-01-05'))->toDecimal())->toBe('30.00')
        ->and($this->valuation->forProduct($this->product, Carbon::parse('2026-01-10'))->toDecimal())->toBe('100.00');
});

/*
|--------------------------------------------------------------------------
| Refusing to go negative
|--------------------------------------------------------------------------
*/

it('refuses to issue more than exists', function () {
    receive(5, '5.00', '2026-01-01');

    expect(fn () => issue(6, '2026-01-02'))
        ->toThrow(InsufficientStockException::class);
});

it('writes nothing at all when an issue is refused', function () {
    receive(5, '5.00', '2026-01-01');

    try {
        issue(6, '2026-01-02');
    } catch (InsufficientStockException) {
        // expected
    }

    expect(StockMovement::query()->where('quantity', '<', 0)->count())->toBe(0)
        ->and(StockBatchConsumption::query()->count())->toBe(0)
        ->and($this->onHand->forProduct($this->product))->toBe(5);
});

it('reports what was asked for and what was there', function () {
    receive(5, '5.00', '2026-01-01');

    try {
        issue(8, '2026-01-02');
        $this->fail('Expected the issue to be refused.');
    } catch (InsufficientStockException $exception) {
        expect($exception->requested)->toBe(8)
            ->and($exception->available)->toBe(5)
            ->and($exception->getMessage())->toContain('Blackout 117x137');
    }
});

it('refuses an issue from a product that has never been stocked', function () {
    expect(fn () => issue(1, '2026-01-01'))
        ->toThrow(InsufficientStockException::class);
});

it('refuses to issue nothing or less', function (int $quantity) {
    receive(5, '5.00', '2026-01-01');

    expect(fn () => issue($quantity, '2026-01-02'))
        ->toThrow(InvalidArgumentException::class);
})->with([0, -1]);

it('will not let repeated issues drive stock below zero', function () {
    receive(10, '5.00', '2026-01-01');

    issue(6, '2026-01-02');
    issue(4, '2026-01-03');

    expect(fn () => issue(1, '2026-01-04'))
        ->toThrow(InsufficientStockException::class);

    expect($this->onHand->forProduct($this->product))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Adjustments
|--------------------------------------------------------------------------
*/

it('adds stock through an adjustment, at a stated cost', function () {
    $movement = $this->service->adjust(
        product: $this->product,
        delta: 3,
        reason: 'Found behind the rack',
        unitCost: Money::fromDecimal('5.00'),
        occurredAt: Carbon::parse('2026-01-01'),
    );

    expect($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->reason)->toBe('Found behind the rack')
        ->and($this->onHand->forProduct($this->product))->toBe(3)
        ->and($this->valuation->forProduct($this->product)->toDecimal())->toBe('15.00');
});

it('refuses to add stock without saying what it is worth', function () {
    expect(fn () => $this->service->adjust(
        product: $this->product,
        delta: 3,
        reason: 'Found behind the rack',
    ))->toThrow(InvalidArgumentException::class);
});

it('removes stock through an adjustment, costed FIFO', function () {
    receive(10, '5.00', '2026-01-01');
    receive(10, '7.00', '2026-01-02');

    $movement = $this->service->adjust(
        product: $this->product,
        delta: -12,
        reason: 'Water damage',
        occurredAt: Carbon::parse('2026-01-03'),
    );

    // 10 at 5.00 + 2 at 7.00
    expect($movement->costOfGoodsSold()->toDecimal())->toBe('64.00')
        ->and($this->onHand->forProduct($this->product))->toBe(8);
});

it('refuses an adjustment that changes nothing', function () {
    expect(fn () => $this->service->adjust($this->product, 0, 'Nothing'))
        ->toThrow(InvalidArgumentException::class);
});

/*
|--------------------------------------------------------------------------
| Ledger integrity
|--------------------------------------------------------------------------
*/

it('costs a receipt at nothing, since only issues have a cost of sale', function () {
    $receipt = receive(10, '5.00', '2026-01-01');

    expect($receipt->costOfGoodsSold()->isZero())->toBeTrue();
});

it('allocates exactly the quantity issued, never more', function () {
    receive(7, '2.00', '2026-01-01');
    receive(7, '3.00', '2026-01-02');

    $sale = issue(9, '2026-01-03');

    expect($sale->consumptions->sum('quantity'))->toBe(9)
        ->and(abs($sale->quantity))->toBe(9);
});

it('keeps each batch consumed within its own size', function () {
    receive(5, '2.00', '2026-01-01');
    receive(5, '3.00', '2026-01-02');

    issue(8, '2026-01-03');

    StockBatch::query()->withSum('consumptions', 'quantity')->get()
        ->each(function (StockBatch $batch) {
            expect($batch->remainingQuantity())->toBeGreaterThanOrEqual(0)
                ->and((int) $batch->consumptions_sum_quantity)
                ->toBeLessThanOrEqual($batch->quantity_received);
        });
});

it('keeps separate products entirely separate', function () {
    $other = Product::factory()->create(['name' => 'Other Curtain']);

    receive(10, '5.00', '2026-01-01');
    $this->service->receive($other, 4, Money::fromDecimal('9.00'), occurredAt: Carbon::parse('2026-01-01'));

    issue(10, '2026-01-02');

    expect($this->onHand->forProduct($this->product))->toBe(0)
        ->and($this->onHand->forProduct($other))->toBe(4)
        ->and($this->valuation->forProduct($other)->toDecimal())->toBe('36.00');
});

it('sums on-hand and valuation across the whole catalogue', function () {
    $other = Product::factory()->create(['name' => 'Another Curtain']);

    receive(10, '5.00', '2026-01-01');
    $this->service->receive($other, 4, Money::fromDecimal('9.00'), occurredAt: Carbon::parse('2026-01-01'));

    expect($this->onHand->get()->sum())->toBe(14)
        ->and($this->valuation->total()->toDecimal())->toBe('86.00');
});

it('links a movement back to whatever caused it', function () {
    $supplier = Supplier::factory()->create();

    $movement = $this->service->receive(
        product: $this->product,
        quantity: 5,
        unitCost: Money::fromDecimal('5.00'),
        type: StockMovementType::Purchase,
        source: $supplier,
    );

    expect($movement->source->is($supplier))->toBeTrue();
});

it('does not fall back to an unfiltered sum when a constrained aggregate is empty', function () {
    // Guards a subtle hazard: valuation constrains the consumption aggregate
    // by date, and an empty result comes back as NULL. Treating that NULL as
    // "not loaded" and re-summing every consumption would quietly ignore the
    // date and understate the valuation.
    receive(10, '5.00', '2026-01-01');
    issue(10, '2026-06-01');

    expect($this->valuation->forProduct($this->product, Carbon::parse('2026-01-01'))->toDecimal())->toBe('50.00')
        ->and($this->valuation->forProduct($this->product, Carbon::parse('2026-06-01'))->toDecimal())->toBe('0.00')
        ->and($this->valuation->forProduct($this->product)->toDecimal())->toBe('0.00');
});

/*
|--------------------------------------------------------------------------
| Landed cost that will not divide evenly
|--------------------------------------------------------------------------
*/

it('splits a receipt into batches when the cost will not divide evenly', function () {
    $movement = $this->service->receiveAtTotalCost(
        product: $this->product,
        quantity: 3,
        totalCost: Money::fromDecimal('10.00'),
        occurredAt: Carbon::parse('2026-01-01'),
    );

    // 1000 across 3 is 333 / 333 / 334 — grouped into two batches so the
    // books still add back to exactly $10.00.
    expect($movement->batches)->toHaveCount(2)
        ->and($movement->receiptCost()->toDecimal())->toBe('10.00')
        ->and($this->valuation->forProduct($this->product)->toDecimal())->toBe('10.00')
        ->and($this->onHand->forProduct($this->product))->toBe(3);
});

it('keeps a single batch when the cost divides cleanly', function () {
    $movement = $this->service->receiveAtTotalCost(
        product: $this->product,
        quantity: 4,
        totalCost: Money::fromDecimal('10.00'),
        occurredAt: Carbon::parse('2026-01-01'),
    );

    expect($movement->batches)->toHaveCount(1)
        ->and($movement->batches->first()->unit_cost->toDecimal())->toBe('2.50');
});

it('never loses or invents a penny, whatever the quantity', function (int $quantity) {
    $this->service->receiveAtTotalCost(
        product: $this->product,
        quantity: $quantity,
        totalCost: Money::fromDecimal('100.00'),
        occurredAt: Carbon::parse('2026-01-01'),
    );

    expect($this->valuation->forProduct($this->product)->toDecimal())->toBe('100.00');
})->with([1, 2, 3, 6, 7, 9, 11, 13, 97, 101]);

it('consumes a split receipt cheapest-first and still totals exactly', function () {
    $this->service->receiveAtTotalCost(
        product: $this->product,
        quantity: 3,
        totalCost: Money::fromDecimal('10.00'),
        occurredAt: Carbon::parse('2026-01-01'),
    );

    $sale = issue(3, '2026-01-02');

    expect($sale->costOfGoodsSold()->toDecimal())->toBe('10.00')
        ->and($this->valuation->forProduct($this->product)->isZero())->toBeTrue();
});

it('refuses a receipt that costs a negative amount', function () {
    expect(fn () => $this->service->receiveAtTotalCost(
        product: $this->product,
        quantity: 3,
        totalCost: Money::fromMinorUnits(-1),
    ))->toThrow(InvalidArgumentException::class);
});
