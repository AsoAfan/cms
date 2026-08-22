<?php

use App\Enums\CostAllocationMethod;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Queries\InventoryValuationQuery;
use App\Queries\StockOnHandQuery;
use App\Services\InventoryService;
use App\Support\Money;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->product = Product::factory()->create(['name' => 'Blackout Eyelet Curtain 117x137']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function purchasePayload(array $overrides = []): array
{
    return array_merge([
        'invoiced_on' => '2026-01-15',
        'status' => PurchaseStatus::Ordered->value,
        'notes' => 'Delivered in two boxes.',
        'lines' => [
            [
                'product_id' => test()->product->id,
                'quantity' => 10,
                'unit_cost' => '18.00',
                'discount' => '0',
            ],
        ],
        'additional_costs' => [
            [
                'label' => 'Freight',
                'amount' => '20.00',
                'allocation_method' => CostAllocationMethod::ByQuantity->value,
            ],
        ],
    ], $overrides);
}

/** Record an invoice through the screen and hand back the model. */
function recordPurchase(array $overrides = []): Purchase
{
    test()->post('/purchases', purchasePayload($overrides))->assertSessionHasNoErrors();

    return Purchase::query()->latest('id')->firstOrFail();
}

function movePurchaseTo(Purchase $purchase, PurchaseStatus $status): void
{
    test()->post("/purchases/{$purchase->id}/status", ['status' => $status->value]);
}

/**
 * Take one off the shelf, so the batch behind it can no longer be undone.
 *
 * Issued through the inventory service rather than through a sale: what makes
 * a receipt un-revertible is the consumption, not the paperwork above it.
 */
function consumeOne(Product $product): void
{
    app(InventoryService::class)->issue(
        product: $product,
        quantity: 1,
        type: StockMovementType::Sale,
    );
}

it('lists purchases with their derived total', function () {
    $purchase = Purchase::factory()->create();
    $purchase->lines()->create([
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_cost' => Money::fromDecimal('10.00'),
        'discount' => Money::zero(),
    ]);

    $this->get('/purchases')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('purchases/index')
            ->has('rows.data', 1)
            ->where('rows.data.0.total', 2000)
            ->where('rows.data.0.status', 'ordered')
            // The drawer is on this screen, so what it needs travels with it.
            ->has('products')
            ->has('statuses', 3)
            ->where('nextNumber', 'PUR-00002')
        );
});

it('records an order without touching stock', function () {
    $purchase = recordPurchase();

    expect($purchase->status)->toBe(PurchaseStatus::Ordered)
        ->and($purchase->number)->toBe('PUR-00001')
        ->and($purchase->lines)->toHaveCount(1)
        ->and($purchase->additionalCosts)->toHaveCount(1)
        ->and($purchase->total()->toDecimal())->toBe('200.00')
        ->and($purchase->committed_at)->toBeNull()
        // Goods that have not arrived are not stock.
        ->and(StockMovement::query()->count())->toBe(0);
});

it('leaves stock alone while an order is on its way', function () {
    $purchase = recordPurchase(['status' => PurchaseStatus::OnTheWay->value]);

    expect($purchase->status)->toBe(PurchaseStatus::OnTheWay)
        ->and($purchase->committed_at)->toBeNull()
        ->and(StockMovement::query()->count())->toBe(0);
});

it('raises stock at the landed cost when the order arrives', function () {
    $purchase = recordPurchase();

    movePurchaseTo($purchase, PurchaseStatus::Proceed);

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Proceed)
        ->and($purchase->fresh()->committed_at)->not->toBeNull()
        ->and(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(10)
        // $180 of goods plus $20 freight over 10 units is $20 each.
        ->and(app(InventoryValuationQuery::class)->forProduct($this->product)->toDecimal())->toBe('200.00');
});

it('takes the stock straight in when an invoice is recorded as arrived', function () {
    $purchase = recordPurchase(['status' => PurchaseStatus::Proceed->value]);

    expect($purchase->committed_at)->not->toBeNull()
        ->and(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(10);
});

it('puts the goods back when an arrived invoice is moved back', function () {
    $purchase = recordPurchase(['status' => PurchaseStatus::Proceed->value]);

    movePurchaseTo($purchase, PurchaseStatus::OnTheWay);

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::OnTheWay)
        ->and($purchase->fresh()->committed_at)->toBeNull()
        ->and(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(0)
        // Undone, not offset: no reversing movement is left behind.
        ->and(StockMovement::query()->count())->toBe(0);
});

it('re-costs the stock when an arrived invoice is edited', function () {
    $purchase = recordPurchase(['status' => PurchaseStatus::Proceed->value]);

    $this->put("/purchases/{$purchase->id}", purchasePayload([
        'status' => PurchaseStatus::Proceed->value,
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 4,
            'unit_cost' => '20.00',
            'discount' => '0',
        ]],
        'additional_costs' => [],
    ]))->assertSessionHasNoErrors();

    expect(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(4)
        ->and(app(InventoryValuationQuery::class)->forProduct($this->product)->toDecimal())->toBe('80.00');
});

it('refuses to edit an invoice whose goods have already been sold', function () {
    $purchase = recordPurchase(['status' => PurchaseStatus::Proceed->value]);

    consumeOne($this->product);

    $this->put("/purchases/{$purchase->id}", purchasePayload([
        'status' => PurchaseStatus::Proceed->value,
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => '1.00',
            'discount' => '0',
        ]],
    ]));

    // The whole edit rolls back, lines included.
    expect($purchase->fresh()->lines->first()->quantity)->toBe(10)
        ->and(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(9);
});

it('refuses to move an invoice back once its goods have been sold', function () {
    $purchase = recordPurchase(['status' => PurchaseStatus::Proceed->value]);

    consumeOne($this->product);

    movePurchaseTo($purchase, PurchaseStatus::Ordered);

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Proceed)
        ->and(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(9);
});

it('deletes an invoice and takes its stock with it', function () {
    $purchase = recordPurchase(['status' => PurchaseStatus::Proceed->value]);

    $this->delete("/purchases/{$purchase->id}")->assertRedirect('/purchases');

    expect(Purchase::query()->count())->toBe(0)
        ->and(PurchaseLine::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(0);
});

it('refuses to delete an invoice whose goods have been sold', function () {
    $purchase = recordPurchase(['status' => PurchaseStatus::Proceed->value]);

    consumeOne($this->product);

    $this->delete("/purchases/{$purchase->id}");

    expect(Purchase::query()->count())->toBe(1);
});

it('updates an order, replacing its lines', function () {
    $purchase = recordPurchase();
    $other = Product::factory()->create();

    $this->put("/purchases/{$purchase->id}", purchasePayload([
        'lines' => [
            ['product_id' => $other->id, 'quantity' => 3, 'unit_cost' => '5.00', 'discount' => '0'],
        ],
        'additional_costs' => [],
    ]))->assertSessionHasNoErrors();

    $purchase = $purchase->fresh()->load('lines');

    expect($purchase->lines)->toHaveCount(1)
        ->and($purchase->lines->first()->product_id)->toBe($other->id)
        ->and($purchase->additionalCosts)->toHaveCount(0);
});

it('shows the invoice with what it comes to', function () {
    $purchase = recordPurchase(['status' => PurchaseStatus::Proceed->value]);

    $this->get("/purchases/{$purchase->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('purchases/show')
            ->where('purchase.status', 'proceed')
            ->where('purchase.total', 20000)
            ->where('purchase.goods_total', 18000)
            ->where('purchase.additional_costs_total', 2000)
            ->where('purchase.total_quantity', 10)
            ->where('purchase.lines.0.net_total', 18000)
            // The edit drawer opens from this page, so it carries its options.
            ->has('products')
            ->has('statuses', 3)
        );
});

it('has no create or edit page', function () {
    $purchase = recordPurchase();

    $this->get('/purchases/create')->assertNotFound();
    $this->get("/purchases/{$purchase->id}/edit")->assertNotFound();
});

it('needs a date, a status and at least one line', function () {
    $this->post('/purchases', ['lines' => []])
        ->assertSessionHasErrors(['invoiced_on', 'status', 'lines']);

    expect(Purchase::query()->count())->toBe(0);
});

it('refuses an unknown status', function () {
    $purchase = recordPurchase();

    $this->post("/purchases/{$purchase->id}/status", ['status' => 'arrived-ish'])
        ->assertSessionHasErrors('status');

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Ordered);
});

it('refuses the same product twice on one invoice', function () {
    $this->post('/purchases', purchasePayload([
        'lines' => [
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => '1.00', 'discount' => '0'],
            ['product_id' => $this->product->id, 'quantity' => 2, 'unit_cost' => '1.00', 'discount' => '0'],
        ],
    ]))->assertSessionHasErrors('lines.0.product_id');
});

it('refuses a quantity below one', function () {
    $this->post('/purchases', purchasePayload([
        'lines' => [
            ['product_id' => $this->product->id, 'quantity' => 0, 'unit_cost' => '1.00', 'discount' => '0'],
        ],
    ]))->assertSessionHasErrors('lines.0.quantity');
});

it('refuses a cost with more precision than a cent', function () {
    $this->post('/purchases', purchasePayload([
        'lines' => [
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => '1.005', 'discount' => '0'],
        ],
    ]))->assertSessionHasErrors('lines.0.unit_cost');
});

it('refuses an unknown allocation method', function () {
    $this->post('/purchases', purchasePayload([
        'additional_costs' => [
            ['label' => 'Freight', 'amount' => '10.00', 'allocation_method' => 'by_vibes'],
        ],
    ]))->assertSessionHasErrors('additional_costs.0.allocation_method');
});

it('filters purchases by status', function () {
    $ordered = recordPurchase();
    $arrived = recordPurchase(['status' => PurchaseStatus::Proceed->value]);

    $this->get('/purchases?status=proceed')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.number', $arrived->number));

    $this->get('/purchases?status=ordered')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.number', $ordered->number));
});

it('keeps guests out of purchasing', function () {
    auth()->logout();

    $this->get('/purchases')->assertRedirect('/login');
    $this->post('/purchases', purchasePayload())->assertRedirect('/login');
});
