<?php

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Queries\GoodsOwedQuery;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Goods owed
|--------------------------------------------------------------------------
|
| Selling something that is out of stock is allowed — an order can be taken for
| goods still coming in — and what it leaves behind is a loan the business is
| carrying: product owed to a customer, and a sale that cannot be sent out
| until the stock is bought. Both halves are derived from the ledger, so buying
| the stock is what clears them.
|
*/

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->inventory = app(InventoryService::class);
    $this->owed = app(GoodsOwedQuery::class);
    $this->customer = Customer::factory()->walkIn()->create();
    $this->product = Product::factory()->create([
        'name' => 'Blackout 117x137',
        'cost_price' => '18.00',
        'selling_price' => '44.00',
    ]);
});

function buyIn(Product $product, int $quantity, string $unitCost = '18.00', string $on = '2026-01-01'): void
{
    test()->inventory->receive(
        product: $product,
        quantity: $quantity,
        unitCost: Money::fromDecimal($unitCost),
        type: StockMovementType::Purchase,
        occurredAt: Carbon::parse($on),
    );
}

/**
 * Ring up an order through the screen. It lands at `ordered`, so it moves no
 * stock and can be short without being refused.
 *
 * @param  list<array{product_id: int, quantity: int}>  $lines
 */
function orderGoods(array $lines, string $soldOn = '2026-02-01'): Sale
{
    test()->post('/sales', [
        'customer_id' => test()->customer->id,
        'sold_on' => $soldOn,
        'status' => SaleStatus::Ordered->value,
        'payment_method' => PaymentMethod::Cash->value,
        'paid_in_full' => true,
        'notes' => null,
        'lines' => array_map(static fn (array $line): array => [
            'product_id' => $line['product_id'],
            'quantity' => $line['quantity'],
            'unit_price' => '44.00',
            'discount' => '0',
        ], $lines),
    ])->assertSessionHasNoErrors();

    return Sale::query()->latest('id')->firstOrFail();
}

it('records what a sale takes that is not on the shelf, valued at cost', function () {
    $sale = orderGoods([['product_id' => $this->product->id, 'quantity' => 5]]);

    $owed = $this->owed->forSale($sale);

    expect($owed)->toHaveCount(1)
        ->and($owed[0]['product'])->toBe('Blackout 117x137')
        ->and($owed[0]['quantity'])->toBe(5)
        ->and($owed[0]['on_hand'])->toBe(0)
        ->and($owed[0]['short'])->toBe(5)
        // At cost, not at the selling price: the debt is what buying the goods
        // in will take, not what the customer is paying for them.
        ->and($owed[0]['value']->toDecimal())->toBe('90.00')
        ->and($this->owed->total()->toDecimal())->toBe('90.00');
});

it('nets off whatever is already on the shelf', function () {
    buyIn($this->product, 2);

    $sale = orderGoods([['product_id' => $this->product->id, 'quantity' => 5]]);

    $owed = $this->owed->forSale($sale);

    expect($owed[0]['on_hand'])->toBe(2)
        ->and($owed[0]['short'])->toBe(3)
        ->and($owed[0]['value']->toDecimal())->toBe('54.00');
});

it('owes nothing for a product it has enough of', function () {
    buyIn($this->product, 10);

    $sale = orderGoods([['product_id' => $this->product->id, 'quantity' => 5]]);

    expect($this->owed->forSale($sale))->toBe([])
        ->and($this->owed->total()->minorUnits)->toBe(0);
});

it('clears itself once the stock is bought in', function () {
    $sale = orderGoods([['product_id' => $this->product->id, 'quantity' => 5]]);

    expect($this->owed->total()->toDecimal())->toBe('90.00');

    buyIn($this->product, 5);

    expect($this->owed->forSale($sale->fresh()))->toBe([])
        ->and($this->owed->total()->minorUnits)->toBe(0);
});

it('owes nothing on a sale whose goods have already gone out', function () {
    buyIn($this->product, 5);

    $sale = orderGoods([['product_id' => $this->product->id, 'quantity' => 5]]);

    $this->post("/sales/{$sale->id}/status", ['status' => SaleStatus::OnTheWay->value]);

    // Its goods are out of the ledger, so measuring what it asked for against
    // what is left would owe the same items a second time.
    expect($sale->fresh()->isCommitted())->toBeTrue()
        ->and($this->owed->forSale($sale->fresh()))->toBe([])
        ->and($this->owed->total()->minorUnits)->toBe(0);
});

it('adds up what every order needs between them', function () {
    buyIn($this->product, 3);

    $first = orderGoods([['product_id' => $this->product->id, 'quantity' => 5]]);
    $second = orderGoods([['product_id' => $this->product->id, 'quantity' => 5]]);

    // Ten wanted against three on the shelf is seven to buy. Read a sale at a
    // time it would be two and two, which is what a shop that buys four then
    // finds itself short again is doing.
    expect($this->owed->forSale($first)[0]['short'])->toBe(2)
        ->and($this->owed->forSale($second)[0]['short'])->toBe(2)
        ->and($this->owed->total()->toDecimal())->toBe('126.00');

    $summary = GoodsOwedQuery::summarise($this->owed->get());

    expect($summary['items'])->toBe(7)
        ->and($summary['products'])->toBe(1);
});

it('lists the worst shortfall first', function () {
    $other = Product::factory()->create(['name' => 'Voile Panel', 'cost_price' => '6.00']);

    orderGoods([
        ['product_id' => $this->product->id, 'quantity' => 2],
        ['product_id' => $other->id, 'quantity' => 9],
    ]);

    expect(array_column($this->owed->get(), 'product'))->toBe(['Voile Panel', 'Blackout 117x137']);
});

it('measures a sale against the stock it had on its own day', function () {
    // Goods that arrive in March cannot have been sold in February, which is
    // what `IssueSaleAction` refuses — so the invoice has to say the same.
    buyIn($this->product, 10, on: '2026-03-01');

    $sale = orderGoods([['product_id' => $this->product->id, 'quantity' => 2]], soldOn: '2026-02-01');

    expect($this->owed->forSale($sale)[0]['short'])->toBe(2)
        // Nothing to buy, though: the shop has the goods and it is the sale's
        // date that is wrong. What must be bought is read as at now.
        ->and($this->owed->total()->minorUnits)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| On the screens
|--------------------------------------------------------------------------
*/

it('shows what the invoice owes on the sale screen', function () {
    buyIn($this->product, 1);

    $sale = orderGoods([['product_id' => $this->product->id, 'quantity' => 5]]);

    $this->get("/sales/{$sale->id}")
        ->assertInertia(fn ($page) => $page
            ->has('sale.owed', 1)
            ->where('sale.owed.0.product', 'Blackout 117x137')
            ->where('sale.owed.0.short', 4)
            ->where('sale.owed.0.on_hand', 1)
            ->where('sale.owed_items', 4)
            ->where('sale.owed_value', 7200)
        );
});

it('shows nothing owed on an invoice it can cover', function () {
    buyIn($this->product, 10);

    $sale = orderGoods([['product_id' => $this->product->id, 'quantity' => 5]]);

    $this->get("/sales/{$sale->id}")
        ->assertInertia(fn ($page) => $page
            ->has('sale.owed', 0)
            ->where('sale.owed_items', 0)
            ->where('sale.owed_value', 0)
        );
});

it('refuses both statuses that would release stock it has not got', function () {
    $sale = orderGoods([['product_id' => $this->product->id, 'quantity' => 5]]);

    foreach ([SaleStatus::OnTheWay, SaleStatus::Proceed] as $status) {
        $this->post("/sales/{$sale->id}/status", ['status' => $status->value])
            ->assertInertiaFlash('toast', [
                'type' => 'error',
                'message' => 'Not enough stock: Blackout 117x137 needs 5, has 0.',
            ]);

        expect($sale->fresh()->status)->toBe(SaleStatus::Ordered)
            ->and($sale->fresh()->isCommitted())->toBeFalse();
    }
});

it('lets the sale go out once the stock has been purchased', function () {
    $sale = orderGoods([['product_id' => $this->product->id, 'quantity' => 5]]);

    buyIn($this->product, 5);

    $this->post("/sales/{$sale->id}/status", ['status' => SaleStatus::OnTheWay->value]);

    expect($sale->fresh()->status)->toBe(SaleStatus::OnTheWay)
        ->and($sale->fresh()->isCommitted())->toBeTrue();
});

it('reports what the business owes in goods on the dashboard and the report', function () {
    buyIn($this->product, 1);

    orderGoods([['product_id' => $this->product->id, 'quantity' => 5]]);

    foreach (['/dashboard', '/reports'] as $screen) {
        $this->get($screen)->assertInertia(fn ($page) => $page
            ->where('goodsOwed.value', 7200)
            ->where('goodsOwed.items', 4)
            ->where('goodsOwed.products', 1)
        );
    }
});

it('reports nothing owed while every order can be covered', function () {
    buyIn($this->product, 10);

    orderGoods([['product_id' => $this->product->id, 'quantity' => 5]]);

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('goodsOwed.value', 0)
        ->where('goodsOwed.items', 0)
    );
});
