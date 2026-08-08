<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Queries\StockOnHandQuery;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(InventoryService::class);
    $this->product = Product::factory()->create([
        'name' => 'Blackout 117x137',
        'code' => 'BEC-117-137',
        'default_cost_price' => '5.00',
    ]);
});

it('lists products with quantity and value from the ledger', function () {
    $this->service->receive($this->product, 10, Money::fromDecimal('5.00'), occurredAt: Carbon::parse('2026-01-01'));

    $this->get('/stock')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('stock/index')
            ->has('rows.data', 1)
            ->where('rows.data.0.on_hand', 10)
            ->where('rows.data.0.value', 5000)
            ->where('totalValue', 5000)
        );
});

it('shows a never-stocked product as zero rather than omitting it', function () {
    $this->get('/stock')
        ->assertInertia(fn ($page) => $page
            ->where('rows.data.0.on_hand', 0)
            ->where('rows.data.0.value', 0)
            ->where('totalValue', 0)
        );
});

it('records a count that adds stock', function () {
    $this->post('/stock', [
        'product_id' => $this->product->id,
        'counted_quantity' => 4,
        'reason' => 'Opening count',
        'unit_cost' => '5.00',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(4);

    $movement = StockMovement::query()->firstOrFail();

    expect($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->quantity)->toBe(4)
        ->and($movement->reason)->toBe('Opening count');
});

it('records a count that removes stock, costed FIFO', function () {
    $this->service->receive($this->product, 10, Money::fromDecimal('5.00'), occurredAt: Carbon::parse('2026-01-01'));

    $this->post('/stock', [
        'product_id' => $this->product->id,
        'counted_quantity' => 7,
        'reason' => 'Water damage',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(app(StockOnHandQuery::class)->forProduct($this->product))->toBe(7);

    $issue = StockMovement::query()->where('quantity', '<', 0)->firstOrFail();

    expect($issue->costOfGoodsSold()->toDecimal())->toBe('15.00');
});

it('needs a cost before it will add stock', function () {
    $this->post('/stock', [
        'product_id' => $this->product->id,
        'counted_quantity' => 4,
        'reason' => 'Found some',
    ])->assertSessionHasErrors('unit_cost');

    expect(StockMovement::query()->count())->toBe(0);
});

it('does not need a cost to remove stock', function () {
    $this->service->receive($this->product, 10, Money::fromDecimal('5.00'), occurredAt: Carbon::parse('2026-01-01'));

    $this->post('/stock', [
        'product_id' => $this->product->id,
        'counted_quantity' => 2,
        'reason' => 'Damaged',
    ])->assertSessionHasNoErrors();
});

it('refuses a count that changes nothing', function () {
    $this->post('/stock', [
        'product_id' => $this->product->id,
        'counted_quantity' => 0,
        'reason' => 'Checked',
    ])->assertSessionHasErrors('counted_quantity');

    expect(StockMovement::query()->count())->toBe(0);
});

it('always needs a reason', function () {
    $this->post('/stock', [
        'product_id' => $this->product->id,
        'counted_quantity' => 4,
        'unit_cost' => '5.00',
    ])->assertSessionHasErrors('reason');
});

it('refuses a negative count', function () {
    $this->post('/stock', [
        'product_id' => $this->product->id,
        'counted_quantity' => -1,
        'reason' => 'Nonsense',
    ])->assertSessionHasErrors('counted_quantity');
});

it('refuses a cost with more precision than a cent', function () {
    $this->post('/stock', [
        'product_id' => $this->product->id,
        'counted_quantity' => 4,
        'reason' => 'Found some',
        'unit_cost' => '5.005',
    ])->assertSessionHasErrors('unit_cost');
});

it('searches stock by name and code', function () {
    Product::factory()->create(['name' => 'Voile Panel', 'code' => 'VOI-117']);

    $this->get('/stock?search=voile')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.code', 'VOI-117'));

    $this->get('/stock?search=BEC')
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)
            ->where('rows.data.0.code', 'BEC-117-137'));
});

it('keeps guests out of stock', function () {
    auth()->logout();

    $this->get('/stock')->assertRedirect('/login');
    $this->post('/stock', [])->assertRedirect('/login');
});

it('refuses to delete a product that has stock history', function () {
    $this->service->receive($this->product, 5, Money::fromDecimal('5.00'), occurredAt: Carbon::parse('2026-01-01'));

    // The ledger must never describe a product that is gone.
    expect(fn () => $this->product->delete())
        ->toThrow(QueryException::class);
});
