<?php

use App\Actions\Purchasing\PostPurchaseAction;
use App\Enums\CostAllocationMethod;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Exceptions\PurchaseNotPostableException;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Queries\InventoryValuationQuery;
use App\Queries\StockOnHandQuery;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->post = app(PostPurchaseAction::class);
    $this->onHand = app(StockOnHandQuery::class);
    $this->valuation = app(InventoryValuationQuery::class);
});

/**
 * @param  list<array{product: Product, quantity: int, unit_cost: string, discount?: string}>  $lines
 * @param  list<array{label: string, amount: string, method: CostAllocationMethod}>  $costs
 */
function draftPurchase(array $lines, array $costs = [], string $on = '2026-01-15'): Purchase
{
    $purchase = Purchase::factory()->create([
        'invoiced_on' => $on,
        'status' => PurchaseStatus::Draft,
    ]);

    foreach ($lines as $line) {
        $purchase->lines()->create([
            'product_id' => $line['product']->id,
            'quantity' => $line['quantity'],
            'unit_cost' => Money::fromDecimal($line['unit_cost']),
            'discount' => Money::fromDecimal($line['discount'] ?? '0'),
        ]);
    }

    foreach ($costs as $cost) {
        $purchase->additionalCosts()->create([
            'label' => $cost['label'],
            'amount' => Money::fromDecimal($cost['amount']),
            'allocation_method' => $cost['method'],
        ]);
    }

    return $purchase->refresh();
}

/*
|--------------------------------------------------------------------------
| The acceptance case from ROADMAP.md
|--------------------------------------------------------------------------
*/

it('raises stock and sets the landed unit cost per batch', function () {
    $product = Product::factory()->create(['name' => 'Blackout Eyelet Curtain 117x137']);

    $purchase = draftPurchase(
        lines: [['product' => $product, 'quantity' => 10, 'unit_cost' => '18.00']],
        costs: [['label' => 'Freight', 'amount' => '20.00', 'method' => CostAllocationMethod::ByQuantity]],
    );

    $this->post->handle($purchase);

    // $180 of goods plus $20 of freight over 10 units is $20 landed each.
    expect($this->onHand->forProduct($product))->toBe(10)
        ->and($this->valuation->forProduct($product)->toDecimal())->toBe('200.00');

    $batch = $product->id
        ? StockBatch::query()->where('product_id', $product->id)->firstOrFail()
        : null;

    expect($batch->unit_cost->toDecimal())->toBe('20.00');
});

/*
|--------------------------------------------------------------------------
| The invariant that matters
|--------------------------------------------------------------------------
*/

it('puts exactly the invoice total into inventory, however awkward the numbers', function (
    array $lines,
    array $costs,
    string $expectedTotal,
) {
    $products = collect($lines)->map(fn () => Product::factory()->create());

    $purchase = draftPurchase(
        lines: collect($lines)->map(fn (array $line, int $index): array => [
            'product' => $products[$index],
            'quantity' => $line[0],
            'unit_cost' => $line[1],
            'discount' => $line[2] ?? '0',
        ])->all(),
        costs: $costs,
    );

    expect($purchase->total()->toDecimal())->toBe($expectedTotal);

    $this->post->handle($purchase);

    // Nothing may be lost or invented between the invoice and the shelf.
    expect($this->valuation->total()->toDecimal())->toBe($expectedTotal);
})->with([
    'single clean line' => [[[10, '18.00']], [], '180.00'],
    'freight that will not divide' => [
        [[3, '10.00'], [3, '10.00']],
        [['label' => 'Freight', 'amount' => '10.00', 'method' => CostAllocationMethod::ByQuantity]],
        '70.00',
    ],
    'duty by value over uneven lines' => [
        [[7, '3.33'], [11, '1.17']],
        [['label' => 'Duty', 'amount' => '13.37', 'method' => CostAllocationMethod::ByValue]],
        '49.55',
    ],
    'several costs at once' => [
        [[3, '19.99'], [5, '4.05'], [1, '100.00']],
        [
            ['label' => 'Freight', 'amount' => '17.77', 'method' => CostAllocationMethod::ByQuantity],
            ['label' => 'Duty', 'amount' => '23.45', 'method' => CostAllocationMethod::ByValue],
        ],
        '221.44',
    ],
    'with discounts' => [
        [[4, '25.00', '10.00'], [2, '12.50', '2.50']],
        [['label' => 'Freight', 'amount' => '9.99', 'method' => CostAllocationMethod::ByQuantity]],
        '122.49',
    ],
    'a penny of freight over many units' => [
        [[97, '1.00']],
        [['label' => 'Freight', 'amount' => '0.01', 'method' => CostAllocationMethod::ByQuantity]],
        '97.01',
    ],
]);

/*
|--------------------------------------------------------------------------
| How costs are spread
|--------------------------------------------------------------------------
*/

it('spreads freight by quantity', function () {
    $bulky = Product::factory()->create();
    $small = Product::factory()->create();

    $purchase = draftPurchase(
        lines: [
            ['product' => $bulky, 'quantity' => 30, 'unit_cost' => '1.00'],
            ['product' => $small, 'quantity' => 10, 'unit_cost' => '1.00'],
        ],
        costs: [['label' => 'Freight', 'amount' => '40.00', 'method' => CostAllocationMethod::ByQuantity]],
    );

    $this->post->handle($purchase);

    // 40.00 over 40 units is 1.00 each, so both lines land at 2.00 a unit.
    expect($this->valuation->forProduct($bulky)->toDecimal())->toBe('60.00')
        ->and($this->valuation->forProduct($small)->toDecimal())->toBe('20.00');
});

it('spreads duty by value', function () {
    $dear = Product::factory()->create();
    $cheap = Product::factory()->create();

    $purchase = draftPurchase(
        lines: [
            ['product' => $dear, 'quantity' => 10, 'unit_cost' => '9.00'],
            ['product' => $cheap, 'quantity' => 10, 'unit_cost' => '1.00'],
        ],
        costs: [['label' => 'Duty', 'amount' => '10.00', 'method' => CostAllocationMethod::ByValue]],
    );

    $this->post->handle($purchase);

    // 90:10 by value, so 9.00 of the duty lands on the dear line.
    expect($this->valuation->forProduct($dear)->toDecimal())->toBe('99.00')
        ->and($this->valuation->forProduct($cheap)->toDecimal())->toBe('11.00');
});

it('takes the discount off before spreading anything over the line', function () {
    $product = Product::factory()->create();

    $purchase = draftPurchase(
        lines: [['product' => $product, 'quantity' => 10, 'unit_cost' => '10.00', 'discount' => '20.00']],
        costs: [['label' => 'Freight', 'amount' => '10.00', 'method' => CostAllocationMethod::ByQuantity]],
    );

    $this->post->handle($purchase);

    // 100 less 20 discount plus 10 freight is 90 for 10 units.
    expect($this->valuation->forProduct($product)->toDecimal())->toBe('90.00');
});

it('spreads a by-value cost evenly when every line is worth nothing', function () {
    $a = Product::factory()->create();
    $b = Product::factory()->create();

    $purchase = draftPurchase(
        lines: [
            ['product' => $a, 'quantity' => 2, 'unit_cost' => '0.00'],
            ['product' => $b, 'quantity' => 2, 'unit_cost' => '0.00'],
        ],
        costs: [['label' => 'Freight', 'amount' => '10.00', 'method' => CostAllocationMethod::ByValue]],
    );

    $this->post->handle($purchase);

    expect($this->valuation->forProduct($a)->toDecimal())->toBe('5.00')
        ->and($this->valuation->forProduct($b)->toDecimal())->toBe('5.00');
});

/*
|--------------------------------------------------------------------------
| What posting writes
|--------------------------------------------------------------------------
*/

it('writes one ledger movement per line, traceable back to it', function () {
    $a = Product::factory()->create();
    $b = Product::factory()->create();

    $purchase = draftPurchase(lines: [
        ['product' => $a, 'quantity' => 5, 'unit_cost' => '2.00'],
        ['product' => $b, 'quantity' => 3, 'unit_cost' => '4.00'],
    ]);

    $this->post->handle($purchase);

    $movements = StockMovement::query()->get();

    expect($movements)->toHaveCount(2)
        ->and($movements->every(fn (StockMovement $m) => $m->type === StockMovementType::Purchase))->toBeTrue()
        ->and($movements->first()->source)->toBeInstanceOf(PurchaseLine::class)
        ->and($movements->first()->source->purchase_id)->toBe($purchase->id);
});

it('dates the stock movement to the invoice, not to today', function () {
    $product = Product::factory()->create();

    $purchase = draftPurchase(
        lines: [['product' => $product, 'quantity' => 5, 'unit_cost' => '2.00']],
        on: '2026-01-15',
    );

    $this->post->handle($purchase);

    expect(StockMovement::query()->firstOrFail()->occurred_at->toDateString())->toBe('2026-01-15');
});

it('marks the purchase posted and stamps when', function () {
    $purchase = draftPurchase(lines: [
        ['product' => Product::factory()->create(), 'quantity' => 1, 'unit_cost' => '1.00'],
    ]);

    $posted = $this->post->handle($purchase);

    expect($posted->status)->toBe(PurchaseStatus::Posted)
        ->and($posted->isPosted())->toBeTrue()
        ->and($posted->posted_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Guards
|--------------------------------------------------------------------------
*/

it('refuses to post the same invoice twice', function () {
    $purchase = draftPurchase(lines: [
        ['product' => Product::factory()->create(), 'quantity' => 5, 'unit_cost' => '2.00'],
    ]);

    $this->post->handle($purchase);

    expect(fn () => $this->post->handle($purchase->refresh()))
        ->toThrow(PurchaseNotPostableException::class);

    expect(StockMovement::query()->count())->toBe(1);
});

it('refuses to post an empty invoice', function () {
    $purchase = Purchase::factory()->create(['status' => PurchaseStatus::Draft]);

    expect(fn () => $this->post->handle($purchase))
        ->toThrow(PurchaseNotPostableException::class);

    expect(StockMovement::query()->count())->toBe(0);
});

it('computes the invoice total from its parts', function () {
    $purchase = draftPurchase(
        lines: [
            ['product' => Product::factory()->create(), 'quantity' => 10, 'unit_cost' => '5.00', 'discount' => '7.50'],
            ['product' => Product::factory()->create(), 'quantity' => 2, 'unit_cost' => '19.99'],
        ],
        costs: [['label' => 'Freight', 'amount' => '12.34', 'method' => CostAllocationMethod::ByQuantity]],
    );

    expect($purchase->goodsTotal()->toDecimal())->toBe('82.48')
        ->and($purchase->additionalCostsTotal()->toDecimal())->toBe('12.34')
        ->and($purchase->total()->toDecimal())->toBe('94.82')
        ->and($purchase->totalQuantity())->toBe(12);
});

it('hands each purchase its own filing number', function () {
    $first = Purchase::factory()->create(['number' => Purchase::nextNumber()]);
    $second = Purchase::factory()->create(['number' => Purchase::nextNumber()]);

    expect($first->number)->toBe('PUR-00001')
        ->and($second->number)->toBe('PUR-00002');
});

/*
|--------------------------------------------------------------------------
| Purchased stock behaves like any other stock
|--------------------------------------------------------------------------
*/

it('lets purchased stock be sold at the landed cost, oldest first', function () {
    $product = Product::factory()->create();

    $january = draftPurchase(
        lines: [['product' => $product, 'quantity' => 10, 'unit_cost' => '5.00']],
        on: '2026-01-01',
    );
    $february = draftPurchase(
        lines: [['product' => $product, 'quantity' => 10, 'unit_cost' => '7.00']],
        on: '2026-02-01',
    );

    $this->post->handle($january);
    $this->post->handle($february);

    $sale = app(InventoryService::class)->issue(
        product: $product,
        quantity: 15,
        type: StockMovementType::Sale,
        occurredAt: Carbon::parse('2026-03-01'),
    );

    expect($sale->costOfGoodsSold()->toDecimal())->toBe('85.00')
        ->and($this->onHand->forProduct($product))->toBe(5);
});
