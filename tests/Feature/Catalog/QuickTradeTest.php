<?php

use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Queries\InventoryValuationQuery;
use App\Queries\StockOnHandQuery;
use App\Services\InventoryService;
use App\Support\Flash;
use App\Support\Money;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->product = Product::factory()->create([
        'name' => 'Blackout Eyelet Curtain 117x137',
        'cost_price' => '18.00',
        'selling_price' => '44.00',
    ]);
    $this->supplier = Supplier::factory()->create();
});

/**
 * Put stock on the shelf without going through the screen under test.
 */
function receiveStock(Product $product, int $quantity, string $unitCost = '18.00', string $on = '2026-01-01'): void
{
    app(InventoryService::class)->receive(
        $product,
        $quantity,
        Money::fromDecimal($unitCost),
        occurredAt: Carbon::parse($on),
    );
}

/*
|--------------------------------------------------------------------------
| Buying
|--------------------------------------------------------------------------
*/

it('buys a product and puts the stock on the shelf', function () {
    $this->post("/products/{$this->product->id}/purchase", [
        'supplier_id' => $this->supplier->id,
        'quantity' => 10,
        'unit_cost' => '18.00',
        'invoiced_on' => '2026-02-01',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(10);

    $purchase = Purchase::query()->firstOrFail();

    // A real invoice, posted — not a shortcut around one.
    expect($purchase->status)->toBe(PurchaseStatus::Posted)
        ->and($purchase->supplier_id)->toBe($this->supplier->id)
        ->and($purchase->invoiced_on->toDateString())->toBe('2026-02-01')
        ->and($purchase->lines)->toHaveCount(1)
        ->and($purchase->total()->toDecimal())->toBe('180.00');
});

it('values the stock it buys at what was paid', function () {
    $this->post("/products/{$this->product->id}/purchase", [
        'supplier_id' => $this->supplier->id,
        'quantity' => 3,
        'unit_cost' => '20.00',
        'invoiced_on' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    expect(app(InventoryValuationQuery::class)
        ->forProduct($this->product)->toDecimal())->toBe('60.00');
});

it('buys without naming a supplier', function () {
    $this->post("/products/{$this->product->id}/purchase", [
        // What the dialog sends when the supplier is left off.
        'supplier_id' => '',
        'quantity' => 10,
        'unit_cost' => '18.00',
        'invoiced_on' => '2026-02-01',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $purchase = Purchase::query()->firstOrFail();

    expect($purchase->supplier_id)->toBeNull()
        ->and($purchase->status)->toBe(PurchaseStatus::Posted)
        ->and(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(10);
});

it('refuses a cost with more precision than a cent', function () {
    $this->post("/products/{$this->product->id}/purchase", [
        'supplier_id' => $this->supplier->id,
        'quantity' => 10,
        'unit_cost' => '18.005',
        'invoiced_on' => '2026-02-01',
    ])->assertSessionHasErrors('unit_cost');
});

it('refuses to buy nothing', function () {
    $this->post("/products/{$this->product->id}/purchase", [
        'supplier_id' => $this->supplier->id,
        'quantity' => 0,
        'unit_cost' => '18.00',
        'invoiced_on' => '2026-02-01',
    ])->assertSessionHasErrors('quantity');

    expect(Purchase::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Selling
|--------------------------------------------------------------------------
*/

it('sells a product and takes the stock off the shelf', function () {
    receiveStock($this->product, 10);

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 4,
        'unit_price' => '44.00',
        'payment_method' => 'cash',
        'sold_on' => '2026-02-01',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(6);

    $sale = Sale::query()->firstOrFail();

    expect($sale->status)->toBe(SaleStatus::Posted)
        ->and($sale->lines)->toHaveCount(1)
        ->and($sale->total()->toDecimal())->toBe('176.00')
        // Cost comes off the batches that actually paid, never the catalogue.
        ->and($sale->costOfGoodsSold()->toDecimal())->toBe('72.00')
        ->and($sale->grossProfit()->toDecimal())->toBe('104.00');
});

it('costs a quick sale FIFO across batches', function () {
    receiveStock($this->product, 10, '5.00');
    receiveStock($this->product, 10, '7.00', on: '2026-01-15');

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 15,
        'unit_price' => '44.00',
        'payment_method' => 'cash',
        'sold_on' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    // Ten at $5 then five at $7 is $85, not fifteen at an average.
    expect(Sale::query()->firstOrFail()->costOfGoodsSold()->toDecimal())->toBe('85.00');
});

it('refuses to sell more than is on the shelf, and leaves nothing behind', function () {
    receiveStock($this->product, 2);

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 5,
        'unit_price' => '44.00',
        'payment_method' => 'cash',
        'sold_on' => '2026-02-01',
    ])->assertSessionHasErrors('quantity');

    // The failed post must not strand a draft nobody asked for.
    expect(Sale::query()->count())->toBe(0)
        ->and(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(2);
});

it('needs a payment method it recognises', function () {
    receiveStock($this->product, 10);

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 1,
        'unit_price' => '44.00',
        'payment_method' => 'barter',
        'sold_on' => '2026-02-01',
    ])->assertSessionHasErrors('payment_method');

    expect(Sale::query()->count())->toBe(0);
});

it('records what the customer was actually charged, not the catalogue price', function () {
    receiveStock($this->product, 10);

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 1,
        'unit_price' => '40.00',
        'payment_method' => 'card',
        'sold_on' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    $line = Sale::query()->firstOrFail()->lines->first();

    expect($line->unit_price->toDecimal())->toBe('40.00');
});

it('says what it did', function () {
    receiveStock($this->product, 10);

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 2,
        'unit_price' => '44.00',
        'payment_method' => 'cash',
        'sold_on' => '2026-02-01',
    ])->assertInertiaFlash(Flash::KEY, [
        'type' => 'success',
        'message' => 'Sold 2 × Blackout Eyelet Curtain 117x137 on '.Sale::query()->value('number').'.',
    ]);
});

it('keeps guests from buying and selling', function () {
    auth()->logout();

    $this->post("/products/{$this->product->id}/purchase", [])->assertRedirect('/login');
    $this->post("/products/{$this->product->id}/sell", [])->assertRedirect('/login');
});
