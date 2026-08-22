<?php

use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Bank;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Queries\InventoryValuationQuery;
use App\Queries\StockOnHandQuery;
use App\Services\InventoryService;
use App\Support\Flash;
use App\Support\Money;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Buying and selling from the catalogue
|--------------------------------------------------------------------------
|
| Both write a real document at `ordered`, not a finished one. The catalogue
| is where an order is taken; the invoice's own screen is where the goods move
| and the money is recorded. Nothing here touches the ledger.
|
*/

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->product = Product::factory()->create([
        'name' => 'Blackout Eyelet Curtain 117x137',
        'cost_price' => '18.00',
        'selling_price' => '44.00',
    ]);
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

it('orders a product without putting anything on the shelf', function () {
    $this->post("/products/{$this->product->id}/purchase", [
        'quantity' => 10,
        'unit_cost' => '18.00',
        'invoiced_on' => '2026-02-01',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $purchase = Purchase::query()->firstOrFail();

    // A real invoice, at the state a real one starts in — not a shortcut that
    // lands stock the invoice screen would have made you confirm.
    expect($purchase->status)->toBe(PurchaseStatus::Ordered)
        ->and($purchase->invoiced_on->toDateString())->toBe('2026-02-01')
        ->and($purchase->lines)->toHaveCount(1)
        ->and($purchase->total()->toDecimal())->toBe('180.00')
        ->and($purchase->committed_at)->toBeNull()
        ->and(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(0);
});

it('values the stock at what was paid once the order arrives', function () {
    $this->post("/products/{$this->product->id}/purchase", [
        'quantity' => 3,
        'unit_cost' => '20.00',
        'invoiced_on' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    $purchase = Purchase::query()->firstOrFail();

    $this->post("/purchases/{$purchase->id}/status", [
        'status' => PurchaseStatus::Proceed->value,
    ])->assertSessionHasNoErrors();

    expect(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(3)
        ->and(app(InventoryValuationQuery::class)
            ->forProduct($this->product)->toDecimal())->toBe('60.00');
});

it('dates the invoice to the day it was given, not to today', function () {
    $this->post("/products/{$this->product->id}/purchase", [
        'quantity' => 10,
        'unit_cost' => '18.00',
        'invoiced_on' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    expect(Purchase::query()->firstOrFail()->invoiced_on->toDateString())
        ->toBe('2026-02-01');
});

it('refuses a cost with more precision than a cent', function () {
    $this->post("/products/{$this->product->id}/purchase", [
        'quantity' => 10,
        'unit_cost' => '18.005',
        'invoiced_on' => '2026-02-01',
    ])->assertSessionHasErrors('unit_cost');
});

it('refuses to buy nothing', function () {
    $this->post("/products/{$this->product->id}/purchase", [
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

it('orders a sale without taking anything off the shelf', function () {
    receiveStock($this->product, 10);

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 4,
        'unit_price' => '44.00',
        'payment_method' => 'cash',
        'sold_on' => '2026-02-01',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $sale = Sale::query()->firstOrFail();

    expect($sale->status)->toBe(SaleStatus::Ordered)
        ->and($sale->lines)->toHaveCount(1)
        ->and($sale->total()->toDecimal())->toBe('176.00')
        ->and($sale->committed_at)->toBeNull()
        // Nothing has left, so nothing has been costed either.
        ->and($sale->costOfGoodsSold()->isZero())->toBeTrue()
        ->and(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(10);
});

it('records nothing as paid, because nothing has been handed over', function () {
    receiveStock($this->product, 10);

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 2,
        'unit_price' => '44.00',
        'payment_method' => 'cash',
        'sold_on' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    $sale = Sale::query()->firstOrFail();

    expect($sale->amount_paid->isZero())->toBeTrue()
        ->and($sale->paidToDate()->isZero())->toBeTrue()
        // Owed only once the goods are the customer's, which they are not yet.
        ->and($sale->outstanding()->isZero())->toBeTrue()
        // And once they are, the whole invoice is on their account.
        ->and($sale->fresh()->forceFill(['status' => SaleStatus::Proceed])
            ->outstanding()->toDecimal())->toBe('88.00');
});

it('costs a quick sale FIFO across batches once it goes out', function () {
    receiveStock($this->product, 10, '5.00');
    receiveStock($this->product, 10, '7.00', on: '2026-01-15');

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 15,
        'unit_price' => '44.00',
        'payment_method' => 'cash',
        'sold_on' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    $sale = Sale::query()->firstOrFail();

    $this->post("/sales/{$sale->id}/status", [
        'status' => SaleStatus::OnTheWay->value,
    ])->assertSessionHasNoErrors();

    // Ten at $5 then five at $7 is $85, not fifteen at an average.
    expect($sale->fresh()->costOfGoodsSold()->toDecimal())->toBe('85.00');
});

it('takes an order for more than is on the shelf, and refuses to send it out', function () {
    receiveStock($this->product, 2);

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 5,
        'unit_price' => '44.00',
        'payment_method' => 'cash',
        'sold_on' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    $sale = Sale::query()->firstOrFail();

    // The order is fine — goods still to come in can be sold. Sending them out
    // is what there has to be stock for.
    $this->post("/sales/{$sale->id}/status", ['status' => SaleStatus::OnTheWay->value]);

    expect($sale->fresh()->status)->toBe(SaleStatus::Ordered)
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
        'bank_id' => Bank::factory()->create()->id,
        'sold_on' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    $line = Sale::query()->firstOrFail()->lines->first();

    expect($line->unit_price->toDecimal())->toBe('40.00');
});

/*
|--------------------------------------------------------------------------
| Saying what it did
|--------------------------------------------------------------------------
|
| Both of these used to move stock and now do not, so the message has to say
| where that happens instead — otherwise the shelf not moving reads as a bug.
|
*/

it('says the sale was ordered and where the stock leaves', function () {
    receiveStock($this->product, 10);

    $this->post("/products/{$this->product->id}/sell", [
        'quantity' => 2,
        'unit_price' => '44.00',
        'payment_method' => 'cash',
        'sold_on' => '2026-02-01',
    ])->assertInertiaFlash(Flash::KEY, [
        'type' => 'success',
        'message' => 'Ordered 2 × Blackout Eyelet Curtain 117x137 on '
            .Sale::query()->value('number').'. Stock leaves when you mark it On the way.',
    ]);
});

it('says the purchase was ordered and where the stock arrives', function () {
    $this->post("/products/{$this->product->id}/purchase", [
        'quantity' => 10,
        'unit_cost' => '18.00',
        'invoiced_on' => '2026-02-01',
    ])->assertInertiaFlash(Flash::KEY, [
        'type' => 'success',
        'message' => 'Ordered 10 × Blackout Eyelet Curtain 117x137 on '
            .Purchase::query()->value('number').'. Stock arrives when you mark it Proceed.',
    ]);
});

it('keeps guests from buying and selling', function () {
    auth()->logout();

    $this->post("/products/{$this->product->id}/purchase", [])->assertRedirect('/login');
    $this->post("/products/{$this->product->id}/sell", [])->assertRedirect('/login');
});
