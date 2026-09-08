<?php

use App\Enums\CostAllocationMethod;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\Bank;
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
        // How the supplier was paid. Cash names no account, which is what an
        // invoice recorded before banks existed reads as.
        'payment_method' => PaymentMethod::Cash->value,
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

it('files an invoice under the reference it was given', function () {
    $purchase = recordPurchase(['number' => 'SUP-9931']);

    expect($purchase->number)->toBe('SUP-9931');
});

it('changes the reference on an invoice already recorded', function () {
    $purchase = recordPurchase();

    $this->put("/purchases/{$purchase->id}", purchasePayload(['number' => 'PUR-00099']))
        ->assertSessionHasNoErrors();

    expect($purchase->fresh()->number)->toBe('PUR-00099');
});

it('keeps the number an invoice already has when the reference is cleared', function () {
    $purchase = recordPurchase();

    $this->put("/purchases/{$purchase->id}", purchasePayload(['number' => '']))
        ->assertSessionHasNoErrors();

    expect($purchase->fresh()->number)->toBe('PUR-00001');
});

it('refuses a reference another invoice is already filed under', function () {
    recordPurchase(['number' => 'PUR-00042']);

    $this->post('/purchases', purchasePayload(['number' => 'PUR-00042']))
        ->assertSessionHasErrors('number');

    expect(Purchase::query()->count())->toBe(1);
});

it('lets an invoice keep its own reference through an edit', function () {
    $purchase = recordPurchase(['number' => 'PUR-00042']);

    $this->put("/purchases/{$purchase->id}", purchasePayload(['number' => 'PUR-00042']))
        ->assertSessionHasNoErrors();

    expect($purchase->fresh()->number)->toBe('PUR-00042');
});

it('renames an invoice in place, leaving the stock it brought in alone', function () {
    $purchase = recordPurchase(['status' => PurchaseStatus::Proceed->value]);
    $movements = StockMovement::query()->count();

    // Spaces around it are typing, not part of the reference.
    $this->patch("/purchases/{$purchase->id}/number", ['number' => '  SUP-9931  '])
        ->assertSessionHasNoErrors();

    expect($purchase->fresh()->number)->toBe('SUP-9931')
        ->and(StockMovement::query()->count())->toBe($movements);
});

it('renames an invoice whose goods have already been sold', function () {
    $purchase = recordPurchase(['status' => PurchaseStatus::Proceed->value]);
    consumeOne($this->product);

    // Editing this invoice is refused, because its batches are spoken for.
    // A reference is filing, not stock, and must stay correctable regardless.
    $this->patch("/purchases/{$purchase->id}/number", ['number' => 'SUP-9931'])
        ->assertSessionHasNoErrors();

    expect($purchase->fresh()->number)->toBe('SUP-9931');
});

it('refuses to rename an invoice onto a reference already in use', function () {
    $first = recordPurchase();
    $second = recordPurchase();

    $this->patch("/purchases/{$second->id}/number", ['number' => $first->number])
        ->assertSessionHasErrors('number');

    expect($second->fresh()->number)->toBe('PUR-00002');
});

it('refuses to leave an invoice with no reference at all', function () {
    $purchase = recordPurchase();

    $this->patch("/purchases/{$purchase->id}/number", ['number' => ' '])
        ->assertSessionHasErrors('number');

    expect($purchase->fresh()->number)->toBe('PUR-00001');
});

it('carries on counting from a reference typed in by hand', function () {
    recordPurchase(['number' => 'PUR-00042']);

    expect(Purchase::nextNumber())->toBe('PUR-00043');
});

it('counts off the greatest reference, not the last one written', function () {
    // 'PUR-9' sorts above 'PUR-00010' on characters alone, and following it
    // would hand back a number the invoice before last already used.
    recordPurchase(['number' => 'PUR-00010']);
    recordPurchase(['number' => 'PUR-9']);

    expect(Purchase::nextNumber())->toBe('PUR-00011');
});

it('follows a reference written in a shape of its own', function () {
    recordPurchase(['number' => 'INV/2026/014']);

    // Same shape, same padding — the next page of their book, not ours.
    expect(Purchase::nextNumber())->toBe('INV/2026/015');
});

it('carries a reference over into another digit', function () {
    recordPurchase(['number' => '999']);

    expect(Purchase::nextNumber())->toBe('1000');
});

it('starts its own sequence when no reference has a number in it', function () {
    recordPurchase(['number' => 'OPENING']);

    expect(Purchase::nextNumber())->toBe('PUR-00001');
});

it('sends the payment options the drawer needs', function () {
    $this->get('/purchases')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('purchases/index')
            ->has('paymentMethods', 3)
            ->has('banks')
        );
});

it('records how an invoice was paid and shows it back', function () {
    $bank = Bank::factory()->create(['name' => 'Cihan Bank']);

    $purchase = recordPurchase([
        'payment_method' => PaymentMethod::Transfer->value,
        'bank_id' => $bank->id,
    ]);

    expect($purchase->payment_method)->toBe(PaymentMethod::Transfer)
        ->and($purchase->bank_id)->toBe($bank->id);

    $this->get("/purchases/{$purchase->id}")
        ->assertInertia(fn ($page) => $page
            ->where('purchase.payment_method', PaymentMethod::Transfer->value)
            ->where('purchase.bank', 'Cihan Bank')
            // A string, because the drawer's select cannot hold a number-or-null.
            ->where('purchase.bank_id', (string) $bank->id)
        );
});

it('lets an invoice paid from an account be switched back to cash', function () {
    $bank = Bank::factory()->create(['name' => 'Cihan Bank']);

    $purchase = recordPurchase([
        'payment_method' => PaymentMethod::Transfer->value,
        'bank_id' => $bank->id,
    ]);

    $this->put("/purchases/{$purchase->id}", purchasePayload([
        'payment_method' => PaymentMethod::Cash->value,
        // What the form sends once the bank field disappears.
        'bank_id' => '',
    ]))->assertSessionHasNoErrors();

    expect($purchase->refresh()->bank_id)->toBeNull();
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
