<?php

use App\Actions\Purchasing\PostPurchaseAction;
use App\Enums\CostAllocationMethod;
use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Queries\InventoryValuationQuery;
use App\Queries\StockOnHandQuery;
use App\Support\Money;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->supplier = Supplier::factory()->create(['name' => 'Northwind Textiles']);
    $this->product = Product::factory()->create(['code' => 'BEC-117-137']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function purchasePayload(array $overrides = []): array
{
    return array_merge([
        'supplier_id' => test()->supplier->id,
        'invoiced_on' => '2026-01-15',
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

it('lists purchases with their derived total', function () {
    $purchase = Purchase::factory()->create(['supplier_id' => $this->supplier->id]);
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
            ->where('rows.data.0.supplier', 'Northwind Textiles')
            ->where('rows.data.0.status', 'draft')
        );
});

it('saves a draft without touching stock', function () {
    $this->post('/purchases', purchasePayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $purchase = Purchase::query()->firstOrFail();

    expect($purchase->status)->toBe(PurchaseStatus::Draft)
        ->and($purchase->number)->toBe('PUR-00001')
        ->and($purchase->lines)->toHaveCount(1)
        ->and($purchase->additionalCosts)->toHaveCount(1)
        ->and($purchase->total()->toDecimal())->toBe('200.00')
        // Nothing reaches the ledger until it is posted.
        ->and(StockMovement::query()->count())->toBe(0);
});

it('posts a draft and raises stock at the landed cost', function () {
    $this->post('/purchases', purchasePayload());
    $purchase = Purchase::query()->firstOrFail();

    $this->post("/purchases/{$purchase->id}/post")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Posted)
        ->and(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(10)
        // $180 of goods plus $20 freight over 10 units is $20 each.
        ->and(app(InventoryValuationQuery::class)->forProduct($this->product)->toDecimal())->toBe('200.00');
});

it('refuses to edit a posted purchase', function () {
    $this->post('/purchases', purchasePayload());
    $purchase = Purchase::query()->firstOrFail();
    $this->post("/purchases/{$purchase->id}/post");

    $this->put("/purchases/{$purchase->id}", purchasePayload([
        'lines' => [[
            'product_id' => $this->product->id,
            'quantity' => 999,
            'unit_cost' => '1.00',
            'discount' => '0',
        ]],
    ]))->assertRedirect("/purchases/{$purchase->id}");

    expect($purchase->fresh()->lines->first()->quantity)->toBe(10);
});

it('sends the edit screen for a posted purchase to the read-only view', function () {
    $this->post('/purchases', purchasePayload());
    $purchase = Purchase::query()->firstOrFail();
    $this->post("/purchases/{$purchase->id}/post");

    $this->get("/purchases/{$purchase->id}/edit")
        ->assertRedirect("/purchases/{$purchase->id}");
});

it('refuses to delete a posted purchase', function () {
    $this->post('/purchases', purchasePayload());
    $purchase = Purchase::query()->firstOrFail();
    $this->post("/purchases/{$purchase->id}/post");

    $this->delete("/purchases/{$purchase->id}")
        ->assertRedirect("/purchases/{$purchase->id}");

    expect(Purchase::query()->count())->toBe(1);
});

it('deletes a draft and its lines', function () {
    $this->post('/purchases', purchasePayload());
    $purchase = Purchase::query()->firstOrFail();

    $this->delete("/purchases/{$purchase->id}")->assertRedirect('/purchases');

    expect(Purchase::query()->count())->toBe(0)
        ->and(PurchaseLine::query()->count())->toBe(0);
});

it('refuses to post the same purchase twice through the screen', function () {
    $this->post('/purchases', purchasePayload());
    $purchase = Purchase::query()->firstOrFail();

    $this->post("/purchases/{$purchase->id}/post");
    $this->post("/purchases/{$purchase->id}/post")->assertRedirect();

    expect(StockMovement::query()->count())->toBe(1);
});

it('updates a draft, replacing its lines', function () {
    $this->post('/purchases', purchasePayload());
    $purchase = Purchase::query()->firstOrFail();
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

it('shows a posted purchase with what each line put on the shelf', function () {
    $this->post('/purchases', purchasePayload());
    $purchase = Purchase::query()->firstOrFail();
    $this->post("/purchases/{$purchase->id}/post");

    $this->get("/purchases/{$purchase->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('purchases/show')
            ->where('purchase.status', 'posted')
            ->where('purchase.total', 20000)
            ->where('purchase.lines.0.landed_total', 20000)
            ->where('purchase.lines.0.batches.0.quantity', 10)
            ->where('purchase.lines.0.batches.0.unit_cost', 2000)
        );
});

it('needs a supplier, a date and at least one line', function () {
    $this->post('/purchases', ['lines' => []])
        ->assertSessionHasErrors(['supplier_id', 'invoiced_on', 'lines']);

    expect(Purchase::query()->count())->toBe(0);
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

it('filters purchases by status and supplier', function () {
    $draft = Purchase::factory()->create(['supplier_id' => $this->supplier->id]);
    $draft->lines()->create([
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_cost' => Money::fromDecimal('1.00'),
        'discount' => Money::zero(),
    ]);

    $posted = Purchase::factory()->create(['supplier_id' => Supplier::factory()->create()->id]);
    $posted->lines()->create([
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_cost' => Money::fromDecimal('1.00'),
        'discount' => Money::zero(),
    ]);
    app(PostPurchaseAction::class)->handle($posted);

    $this->get('/purchases?status=posted')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.number', $posted->number));

    $this->get("/purchases?supplier_id={$this->supplier->id}")
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.number', $draft->number));
});

it('refuses to delete a supplier with purchases', function () {
    $this->post('/purchases', purchasePayload());

    expect(fn () => $this->supplier->delete())
        ->toThrow(QueryException::class);
});

it('keeps guests out of purchasing', function () {
    auth()->logout();

    $this->get('/purchases')->assertRedirect('/login');
    $this->post('/purchases', purchasePayload())->assertRedirect('/login');
});
