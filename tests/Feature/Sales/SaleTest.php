<?php

use App\Actions\Sales\PostSaleAction;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\SaleNotPostableException;
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
    $this->product = Product::factory()->create([
        'name' => 'Blackout 117x137',
        'code' => 'BEC-117-137',
        'default_selling_price' => '44.00',
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
        'sold_on' => '2026-02-01',
        'payment_method' => PaymentMethod::Cash->value,
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

/*
|--------------------------------------------------------------------------
| Posting: stock out, cost in
|--------------------------------------------------------------------------
*/

it('takes stock out and records what it cost', function () {
    stockUp($this->product, 10, '18.00');

    $this->post('/sales', salePayload())->assertRedirect();
    $sale = Sale::query()->firstOrFail();

    $this->post("/sales/{$sale->id}/post")->assertRedirect()->assertSessionHasNoErrors();

    $sale = $sale->fresh()->load('lines.stockMovements.consumptions.batch');

    expect($sale->status)->toBe(SaleStatus::Posted)
        ->and($this->onHand->forProduct($this->product))->toBe(8)
        ->and($sale->total()->toDecimal())->toBe('88.00')
        ->and($sale->costOfGoodsSold()->toDecimal())->toBe('36.00')
        ->and($sale->grossProfit()->toDecimal())->toBe('52.00');
});

it('costs a sale FIFO across batches', function () {
    stockUp($this->product, 10, '5.00', '2026-01-01');
    stockUp($this->product, 10, '7.00', '2026-01-02');

    $this->post('/sales', salePayload([
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 15,
            'unit_price' => '20.00',
            'discount' => '0',
        ]],
    ]));

    $sale = Sale::query()->firstOrFail();
    $this->post("/sales/{$sale->id}/post");

    // 10 at 5.00 then 5 at 7.00 is 85.00, exactly as the ledger says.
    expect($sale->fresh()->costOfGoodsSold()->toDecimal())->toBe('85.00')
        ->and($this->onHand->forProduct($this->product))->toBe(5)
        ->and($this->valuation->forProduct($this->product)->toDecimal())->toBe('35.00');
});

it('leaves stock alone until the sale is posted', function () {
    stockUp($this->product, 10, '18.00');

    $this->post('/sales', salePayload());

    expect($this->onHand->forProduct($this->product))->toBe(10)
        ->and(StockMovement::query()->where('quantity', '<', 0)->count())->toBe(0);
});

it('reports no cost or profit on a draft', function () {
    stockUp($this->product, 10, '18.00');
    $this->post('/sales', salePayload());

    $sale = Sale::query()->firstOrFail();

    expect($sale->costOfGoodsSold()->isZero())->toBeTrue()
        ->and($sale->grossProfit()->toDecimal())->toBe('88.00');
});

it('takes the discount off the takings but not off the cost', function () {
    stockUp($this->product, 10, '18.00');

    $this->post('/sales', salePayload([
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => '44.00',
            'discount' => '8.00',
        ]],
    ]));

    $sale = Sale::query()->firstOrFail();
    $this->post("/sales/{$sale->id}/post");

    $sale = $sale->fresh();

    expect($sale->total()->toDecimal())->toBe('80.00')
        ->and($sale->costOfGoodsSold()->toDecimal())->toBe('36.00')
        ->and($sale->grossProfit()->toDecimal())->toBe('44.00');
});

it('dates the stock movement to the sale, not to today', function () {
    stockUp($this->product, 10, '18.00', '2026-01-01');

    $this->post('/sales', salePayload(['sold_on' => '2026-02-15']));
    $sale = Sale::query()->firstOrFail();
    $this->post("/sales/{$sale->id}/post");

    $issue = StockMovement::query()->where('quantity', '<', 0)->firstOrFail();

    expect($issue->occurred_at->toDateString())->toBe('2026-02-15')
        ->and($issue->type)->toBe(StockMovementType::Sale);
});

/*
|--------------------------------------------------------------------------
| Overselling
|--------------------------------------------------------------------------
*/

it('refuses to sell more than there is', function () {
    stockUp($this->product, 1, '18.00');

    $this->post('/sales', salePayload());
    $sale = Sale::query()->firstOrFail();

    $this->post("/sales/{$sale->id}/post")->assertRedirect();

    expect($sale->fresh()->status)->toBe(SaleStatus::Draft)
        ->and($this->onHand->forProduct($this->product))->toBe(1);
});

it('posts nothing at all when one line of several is short', function () {
    $other = Product::factory()->create(['code' => 'VOI-117']);

    stockUp($this->product, 10, '18.00');
    stockUp($other, 1, '6.00');

    $this->post('/sales', salePayload([
        'lines' => [
            ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => '44.00', 'discount' => '0'],
            ['product_id' => $other->id, 'quantity' => 5, 'unit_price' => '16.00', 'discount' => '0'],
        ],
    ]));

    $sale = Sale::query()->firstOrFail();
    $this->post("/sales/{$sale->id}/post");

    // The good line must not go through on its own.
    expect($sale->fresh()->status)->toBe(SaleStatus::Draft)
        ->and($this->onHand->forProduct($this->product))->toBe(10)
        ->and($this->onHand->forProduct($other))->toBe(1);
});

it('reports every short product at once, not just the first', function () {
    $other = Product::factory()->create(['code' => 'VOI-117', 'name' => 'Voile Panel']);

    stockUp($this->product, 1, '18.00');
    stockUp($other, 1, '6.00');

    $sale = Sale::query()->create([
        'number' => Sale::nextNumber(),
        'sold_on' => '2026-02-01',
        'status' => SaleStatus::Draft,
        'payment_method' => PaymentMethod::Cash,
    ]);
    $sale->lines()->createMany([
        ['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => Money::fromDecimal('44.00'), 'discount' => Money::zero()],
        ['product_id' => $other->id, 'quantity' => 5, 'unit_price' => Money::fromDecimal('16.00'), 'discount' => Money::zero()],
    ]);

    try {
        app(PostSaleAction::class)->handle($sale->refresh());
        $this->fail('Expected the sale to be refused.');
    } catch (SaleNotPostableException $exception) {
        expect($exception->shortages)->toHaveCount(2)
            ->and($exception->getMessage())->toContain('BEC-117-137')
            ->and($exception->getMessage())->toContain('VOI-117');
    }
});

it('refuses a sale for stock that had not arrived yet', function () {
    stockUp($this->product, 10, '18.00', '2026-03-01');

    $this->post('/sales', salePayload(['sold_on' => '2026-02-01']));
    $sale = Sale::query()->firstOrFail();

    $this->post("/sales/{$sale->id}/post");

    expect($sale->fresh()->status)->toBe(SaleStatus::Draft);
});

/*
|--------------------------------------------------------------------------
| Status machine
|--------------------------------------------------------------------------
*/

it('refuses to post the same sale twice', function () {
    stockUp($this->product, 10, '18.00');

    $this->post('/sales', salePayload());
    $sale = Sale::query()->firstOrFail();

    $this->post("/sales/{$sale->id}/post");
    $this->post("/sales/{$sale->id}/post")->assertRedirect();

    expect(StockMovement::query()->where('quantity', '<', 0)->count())->toBe(1)
        ->and($this->onHand->forProduct($this->product))->toBe(8);
});

it('refuses to edit or delete a posted sale', function () {
    stockUp($this->product, 10, '18.00');

    $this->post('/sales', salePayload());
    $sale = Sale::query()->firstOrFail();
    $this->post("/sales/{$sale->id}/post");

    $this->put("/sales/{$sale->id}", salePayload([
        'lines' => [['product_id' => $this->product->id, 'quantity' => 9, 'unit_price' => '1.00', 'discount' => '0']],
    ]))->assertRedirect("/sales/{$sale->id}");

    $this->delete("/sales/{$sale->id}")->assertRedirect("/sales/{$sale->id}");

    expect($sale->fresh()->lines->first()->quantity)->toBe(2)
        ->and(Sale::query()->count())->toBe(1);
});

it('sends the edit screen for a posted sale to the read-only view', function () {
    stockUp($this->product, 10, '18.00');
    $this->post('/sales', salePayload());
    $sale = Sale::query()->firstOrFail();
    $this->post("/sales/{$sale->id}/post");

    $this->get("/sales/{$sale->id}/edit")->assertRedirect("/sales/{$sale->id}");
});

it('deletes a draft', function () {
    $this->post('/sales', salePayload());
    $sale = Sale::query()->firstOrFail();

    $this->delete("/sales/{$sale->id}")->assertRedirect('/sales');

    expect(Sale::query()->count())->toBe(0)
        ->and(SaleLine::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Screens
|--------------------------------------------------------------------------
*/

it('lists sales with their total', function () {
    stockUp($this->product, 10, '18.00');
    $this->post('/sales', salePayload());

    $this->get('/sales')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/index')
            ->has('rows.data', 1)
            ->where('rows.data.0.total', 8800)
            ->where('rows.data.0.status', 'draft')
        );
});

it('shows a posted sale with per-line profit', function () {
    stockUp($this->product, 10, '18.00');
    $this->post('/sales', salePayload());
    $sale = Sale::query()->firstOrFail();
    $this->post("/sales/{$sale->id}/post");

    $this->get("/sales/{$sale->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/show')
            ->where('sale.total', 8800)
            ->where('sale.cost_of_goods_sold', 3600)
            ->where('sale.gross_profit', 5200)
            ->where('sale.lines.0.cost_of_goods_sold', 3600)
            ->where('sale.lines.0.gross_profit', 5200)
        );
});

it('offers products with what is on hand, so the till can warn', function () {
    stockUp($this->product, 7, '18.00');

    $this->get('/sales/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/create')
            ->where('products.0.on_hand', 7)
            ->where('products.0.code', 'BEC-117-137')
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

it('hands each sale its own number', function () {
    $this->post('/sales', salePayload());
    $this->post('/sales', salePayload());

    expect(Sale::query()->pluck('number')->all())->toBe(['SAL-00001', 'SAL-00002']);
});

it('refuses to delete a product that has been sold', function () {
    stockUp($this->product, 10, '18.00');
    $this->post('/sales', salePayload());
    $sale = Sale::query()->firstOrFail();
    $this->post("/sales/{$sale->id}/post");

    expect(fn () => $this->product->delete())
        ->toThrow(QueryException::class);
});

it('keeps guests out of sales', function () {
    auth()->logout();

    $this->get('/sales')->assertRedirect('/login');
    $this->post('/sales', salePayload())->assertRedirect('/login');
});
