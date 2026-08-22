<?php

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Queries\InventoryValuationQuery;
use App\Queries\StockOnHandQuery;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->inventory = app(InventoryService::class);
    $this->onHand = app(StockOnHandQuery::class);
    $this->valuation = app(InventoryValuationQuery::class);
    $this->customer = Customer::factory()->walkIn()->create();
    $this->product = Product::factory()->create([
        'name' => 'Blackout 117x137',
        'selling_price' => '44.00',
    ]);
});

function stockUp(Product $product, int $quantity, string $unitCost, string $on = '2026-01-01'): void
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
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function salePayload(array $overrides = []): array
{
    return array_merge([
        // Every sale names a buyer. Counter trade is the walk-in customer's.
        'customer_id' => test()->customer->id,
        'sold_on' => '2026-02-01',
        'status' => SaleStatus::Ordered->value,
        'payment_method' => PaymentMethod::Cash->value,
        'paid_in_full' => true,
        'notes' => null,
        'lines' => [
            [
                'product_id' => test()->product->id,
                'quantity' => 2,
                'unit_price' => '44.00',
                'discount' => '0',
            ],
        ],
    ], $overrides);
}

/** Ring a sale up through the screen and hand back the model. */
function recordSale(array $overrides = []): Sale
{
    test()->post('/sales', salePayload($overrides))->assertSessionHasNoErrors();

    return Sale::query()->latest('id')->firstOrFail();
}

function moveSaleTo(Sale $sale, SaleStatus $status): void
{
    test()->post("/sales/{$sale->id}/status", ['status' => $status->value]);
}

/*
|--------------------------------------------------------------------------
| Stock out, cost in
|--------------------------------------------------------------------------
*/

it('takes stock out and records what it cost when the sale goes out', function () {
    stockUp($this->product, 10, '18.00');

    $sale = recordSale();

    moveSaleTo($sale, SaleStatus::OnTheWay);

    $sale = $sale->fresh()->load('lines.stockMovements.consumptions.batch');

    expect($sale->status)->toBe(SaleStatus::OnTheWay)
        ->and($sale->committed_at)->not->toBeNull()
        ->and($this->onHand->forProduct($this->product))->toBe(8)
        ->and($sale->total()->toDecimal())->toBe('88.00')
        ->and($sale->costOfGoodsSold()->toDecimal())->toBe('36.00')
        ->and($sale->grossProfit()->toDecimal())->toBe('52.00');
});

it('costs a sale FIFO across batches', function () {
    stockUp($this->product, 10, '5.00', '2026-01-01');
    stockUp($this->product, 10, '7.00', '2026-01-02');

    $sale = recordSale([
        'status' => SaleStatus::OnTheWay->value,
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 15,
            'unit_price' => '20.00',
            'discount' => '0',
        ]],
    ]);

    // 10 at 5.00 then 5 at 7.00 is 85.00, exactly as the ledger says.
    expect($sale->costOfGoodsSold()->toDecimal())->toBe('85.00')
        ->and($this->onHand->forProduct($this->product))->toBe(5)
        ->and($this->valuation->forProduct($this->product)->toDecimal())->toBe('35.00');
});

it('leaves stock alone while the sale is only ordered', function () {
    stockUp($this->product, 10, '18.00');

    recordSale();

    expect($this->onHand->forProduct($this->product))->toBe(10)
        ->and(StockMovement::query()->where('quantity', '<', 0)->count())->toBe(0);
});

it('reports no cost or profit on an order', function () {
    stockUp($this->product, 10, '18.00');

    $sale = recordSale();

    expect($sale->costOfGoodsSold()->isZero())->toBeTrue()
        ->and($sale->grossProfit()->toDecimal())->toBe('88.00');
});

it('puts the goods back when a sale is moved back to ordered', function () {
    stockUp($this->product, 10, '18.00');

    $sale = recordSale(['status' => SaleStatus::OnTheWay->value]);

    moveSaleTo($sale, SaleStatus::Ordered);

    expect($sale->fresh()->status)->toBe(SaleStatus::Ordered)
        ->and($sale->fresh()->committed_at)->toBeNull()
        ->and($this->onHand->forProduct($this->product))->toBe(10)
        // Undone, not offset: no reversing movement is left behind.
        ->and(StockMovement::query()->where('quantity', '<', 0)->count())->toBe(0);
});

it('moves nothing when the customer receives goods already sent out', function () {
    stockUp($this->product, 10, '18.00');

    $sale = recordSale(['status' => SaleStatus::OnTheWay->value]);

    moveSaleTo($sale, SaleStatus::Proceed);

    expect($sale->fresh()->status)->toBe(SaleStatus::Proceed)
        ->and($this->onHand->forProduct($this->product))->toBe(8)
        ->and(StockMovement::query()->where('quantity', '<', 0)->count())->toBe(1);
});

it('takes the discount off the takings but not off the cost', function () {
    stockUp($this->product, 10, '18.00');

    $sale = recordSale([
        'status' => SaleStatus::OnTheWay->value,
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => '44.00',
            'discount' => '8.00',
        ]],
    ]);

    expect($sale->total()->toDecimal())->toBe('80.00')
        ->and($sale->costOfGoodsSold()->toDecimal())->toBe('36.00')
        ->and($sale->grossProfit()->toDecimal())->toBe('44.00');
});

it('dates the stock movement to the sale, not to today', function () {
    stockUp($this->product, 10, '18.00', '2026-01-01');

    recordSale([
        'status' => SaleStatus::OnTheWay->value,
        'sold_on' => '2026-02-15',
    ]);

    $issue = StockMovement::query()->where('quantity', '<', 0)->firstOrFail();

    expect($issue->occurred_at->toDateString())->toBe('2026-02-15')
        ->and($issue->type)->toBe(StockMovementType::Sale);
});

/*
|--------------------------------------------------------------------------
| Overselling
|--------------------------------------------------------------------------
*/

it('refuses to send out more than there is', function () {
    stockUp($this->product, 1, '18.00');

    $sale = recordSale();

    moveSaleTo($sale, SaleStatus::OnTheWay);

    expect($sale->fresh()->status)->toBe(SaleStatus::Ordered)
        ->and($this->onHand->forProduct($this->product))->toBe(1);
});

it('sends nothing at all when one line of several is short', function () {
    $other = Product::factory()->create(['name' => 'Voile Panel 117']);

    stockUp($this->product, 10, '18.00');
    stockUp($other, 1, '6.00');

    $sale = recordSale([
        'lines' => [
            ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => '44.00', 'discount' => '0'],
            ['product_id' => $other->id, 'quantity' => 5, 'unit_price' => '16.00', 'discount' => '0'],
        ],
    ]);

    moveSaleTo($sale, SaleStatus::OnTheWay);

    // The good line must not go out on its own.
    expect($sale->fresh()->status)->toBe(SaleStatus::Ordered)
        ->and($this->onHand->forProduct($this->product))->toBe(10)
        ->and($this->onHand->forProduct($other))->toBe(1);
});

it('reports every short product at once, not just the first', function () {
    $other = Product::factory()->create(['name' => 'Voile Panel']);

    stockUp($this->product, 1, '18.00');
    stockUp($other, 1, '6.00');

    $sale = recordSale([
        'lines' => [
            ['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => '44.00', 'discount' => '0'],
            ['product_id' => $other->id, 'quantity' => 5, 'unit_price' => '16.00', 'discount' => '0'],
        ],
    ]);

    // Both shortages in one message: fixing them one round trip at a time is
    // how somebody ends up short on the third product too.
    $this->post("/sales/{$sale->id}/status", ['status' => SaleStatus::OnTheWay->value])
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Not enough stock: Blackout 117x137 needs 5, has 1; Voile Panel needs 5, has 1.',
        ]);
});

it('refuses a sale for stock that had not arrived yet', function () {
    stockUp($this->product, 10, '18.00', '2026-03-01');

    $sale = recordSale(['sold_on' => '2026-02-01']);

    moveSaleTo($sale, SaleStatus::OnTheWay);

    expect($sale->fresh()->status)->toBe(SaleStatus::Ordered);
});

/*
|--------------------------------------------------------------------------
| Correcting and deleting
|--------------------------------------------------------------------------
*/

it('re-issues the stock when a sale that has gone out is edited', function () {
    stockUp($this->product, 10, '18.00');

    $sale = recordSale(['status' => SaleStatus::OnTheWay->value]);

    $this->put("/sales/{$sale->id}", salePayload([
        'status' => SaleStatus::OnTheWay->value,
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => '44.00',
            'discount' => '0',
        ]],
    ]))->assertSessionHasNoErrors();

    expect($sale->fresh()->lines->first()->quantity)->toBe(5)
        ->and($this->onHand->forProduct($this->product))->toBe(5);
});

it('deletes a sale and puts its stock back', function () {
    stockUp($this->product, 10, '18.00');

    $sale = recordSale(['status' => SaleStatus::OnTheWay->value]);

    $this->delete("/sales/{$sale->id}")->assertRedirect('/sales');

    expect(Sale::query()->count())->toBe(0)
        ->and(SaleLine::query()->count())->toBe(0)
        ->and($this->onHand->forProduct($this->product))->toBe(10)
        ->and(StockMovement::query()->where('quantity', '<', 0)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Screens
|--------------------------------------------------------------------------
*/

it('lists sales with their total', function () {
    stockUp($this->product, 10, '18.00');
    $sale = recordSale();

    $this->get('/sales')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/index')
            ->has('rows.data', 1)
            ->where('rows.data.0.total', 8800)
            ->where('rows.data.0.status', 'ordered')
            // The drawer is on this screen, so what it needs travels with it.
            ->has('products')
            ->has('customers')
            ->has('statuses', 3)
            ->where('nextNumber', 'SAL-00002')
        );

    expect($sale->number)->toBe('SAL-00001');
});

it('shows a sale that has gone out with what it cost and made', function () {
    stockUp($this->product, 10, '18.00');
    $sale = recordSale(['status' => SaleStatus::OnTheWay->value]);

    $this->get("/sales/{$sale->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/show')
            ->where('sale.total', 8800)
            ->where('sale.total_quantity', 2)
            ->where('sale.cost_of_goods_sold', 3600)
            ->where('sale.gross_profit', 5200)
            ->where('sale.lines.0.net_total', 8800)
            // The edit drawer opens from this page, so it carries its options.
            ->has('products')
            ->has('statuses', 3)
        );
});

it('has no create or edit page', function () {
    $sale = recordSale();

    $this->get('/sales/create')->assertNotFound();
    $this->get("/sales/{$sale->id}/edit")->assertNotFound();
});

it('offers products with what is on hand, so the till can warn', function () {
    stockUp($this->product, 7, '18.00');

    $this->get('/sales')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/index')
            ->where('products.0.on_hand', 7)
            ->where('products.0.name', 'Blackout 117x137')
            ->has('paymentMethods', 3)
        );
});

it('needs a date, a payment method and at least one line', function () {
    $this->post('/sales', ['lines' => []])
        ->assertSessionHasErrors(['sold_on', 'payment_method', 'lines']);

    expect(Sale::query()->count())->toBe(0);
});

it('refuses the same product twice on one sale', function () {
    $this->post('/sales', salePayload([
        'lines' => [
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => '1.00', 'discount' => '0'],
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => '1.00', 'discount' => '0'],
        ],
    ]))->assertSessionHasErrors('lines.0.product_id');
});

it('refuses a price with more precision than a cent', function () {
    $this->post('/sales', salePayload([
        'lines' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => '1.005', 'discount' => '0']],
    ]))->assertSessionHasErrors('lines.0.unit_price');
});

it('refuses an unknown status', function () {
    $sale = recordSale();

    $this->post("/sales/{$sale->id}/status", ['status' => 'sold-ish'])
        ->assertSessionHasErrors('status');

    expect($sale->fresh()->status)->toBe(SaleStatus::Ordered);
});

it('hands each sale its own number', function () {
    recordSale();
    recordSale();

    expect(Sale::query()->pluck('number')->all())->toBe(['SAL-00001', 'SAL-00002']);
});

it('filters sales by status', function () {
    stockUp($this->product, 10, '18.00');

    $ordered = recordSale();
    $sent = recordSale(['status' => SaleStatus::OnTheWay->value]);

    $this->get('/sales?status=on_the_way')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.number', $sent->number));

    $this->get('/sales?status=ordered')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.number', $ordered->number));
});

it('refuses to delete a product that has been sold', function () {
    stockUp($this->product, 10, '18.00');
    recordSale(['status' => SaleStatus::OnTheWay->value]);

    expect(fn () => $this->product->delete())
        ->toThrow(QueryException::class);
});

it('keeps guests out of sales', function () {
    auth()->logout();

    $this->get('/sales')->assertRedirect('/login');
    $this->post('/sales', salePayload())->assertRedirect('/login');
});
